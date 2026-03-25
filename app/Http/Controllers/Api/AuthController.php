<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApiUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;
use App\Mail\ResetPasswordOtpMail;

use App\Mail\VerifyOtpMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AuthController extends Controller
{

    private int $otpTtlMinutes = 10;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                \Illuminate\Validation\Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $trashed = ApiUser::onlyTrashed()->where('email', $request->email)->first();
        if ($trashed) {
            $trashed->forceDelete();
        }

        if (!Role::where('name', 'user')->where('guard_name', 'api')->exists()) {
            Role::create(['name' => 'user', 'guard_name' => 'api']);
        }

        $user = ApiUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'user',
            'is_verified' => false,
        ]);

        $user->assignRole('user');

        // Generate & email OTP
        $this->issueAndSendOtp($user);

        return response()->json([
            'status' => true,
            'message' => 'User registered. A verification code has been emailed to you.',
            'data' => [
                'user' => $user->only(['id', 'name', 'email', 'is_verified']),
            ]
        ], 201);
    }


public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    // Check if email exists
    $user = ApiUser::where('email', $credentials['email'])->first();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'Unknown Email Address.'
        ], 404);
    }

    // Now attempt login
    if (!$token = auth('api')->attempt($credentials)) {
        return response()->json([
            'status' => false,
            'message' => 'The password you have entered for this email is incorrect.'
        ], 401);
    }

    /** @var ApiUser $user */
    $user = auth('api')->user();

    if (!$user->hasRole('user')) {
        return response()->json([
            'status' => false,
            'message' => 'Access denied. Insufficient permissions.'
        ], 403);
    }

    // Do not allow login if user is soft-deleted
    if (method_exists($user, 'trashed') && $user->trashed()) {
        auth('api')->logout();
        return response()->json([
            'status' => false,
            'message' => 'Your account has been deactivated. Please contact support.'
        ], 410);
    }

    // Do not allow login if blocked
    if (!empty($user->is_blocked) && $user->is_blocked) {
        auth('api')->logout();
        return response()->json([
            'status' => false,
            'message' => 'Your account is blocked. Please contact support.'
        ], 403);
    }

    if (!$user->is_verified) {
        if (!$user->otp_expires_at || Carbon::parse($user->otp_expires_at)->isPast()) {
            $this->issueAndSendOtp($user);
        }
        auth('api')->logout();

        return response()->json([
            'status' => false,
            'message' => 'Email not verified. We have sent (or re-sent) a verification code to your email.',
        ], 403);
    }

    // Load related models including the user's theme
    $user->load([
        'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
        'reviews_api' => fn ($q) => $q->latest()->with(['video:id,title']),
        'roles',
        'userTheme.theme',
    ]);

    $data = $this->shapeUser($user);

    $theme = null;

    if (!empty($user->userTheme) && !empty($user->userTheme->theme)) {
        $t = $user->userTheme->theme;
        $theme = [
            'id'             => $t->id,
            'name'           => $t->name,
            'button_color'   => $t->button_color,
            'link_color'     => $t->link_color,
            'dark_bg_color'  => $t->dark_bg_color,
            'light_bg_color' => $t->light_bg_color,
        ];
    }

    return response()->json([
        'status'  => true,
        'message' => 'Login successful.',
        'data'    => [
            'token' => $token,
            'user'  => $data,
            'theme' => $theme,
        ]
    ], 200);
}


private function shapeUser(ApiUser $user): array
    {
        // simple “active” flag (active OR trialing and within dates)
        $hasActiveSubscription = (bool) optional($user->subscriptions_api->first(), function ($sub) {
            $now = now();
            $statusOkay  = in_array($sub->subscription_status, ['active','trialing'], true);
            $notCanceled = $sub->subscription_status !== 'canceled' && is_null($sub->canceled_at);
            $withinPaid  = $sub->subscription_end_date && $now->lte($sub->subscription_end_date);
            $withinTrial = $sub->trial_end_date && $now->lte($sub->trial_end_date);
            return $statusOkay && $notCanceled && ($withinPaid || $withinTrial);
        });

        return [
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'roles'    => $user->roles->pluck('name')->values(),

            'has_active_subscription' => $hasActiveSubscription,

            'subscriptions' => $user->subscriptions_api->map(function ($s) {
                return [
                    'id'                     => $s->id,
                    'subscription_name'      => $s->subscription_name,
                    'subscription_period'    => $s->subscription_period,
                    'billing_cycle'          => $s->billing_cycle,
                    'subscription_start_date'=> optional($s->subscription_start_date)->toDateString(),
                    'subscription_end_date'  => optional($s->subscription_end_date)->toDateString(),
                    'trial_start_date'       => optional($s->trial_start_date)->toDateString(),
                    'trial_end_date'         => optional($s->trial_end_date)->toDateString(),
                    'payment_status'         => $s->payment_status,
                    'subscription_status'    => $s->subscription_status,
                    'auto_renew'             => (bool) $s->auto_renew,
                    'cancel_at_period_end'   => (bool) $s->cancel_at_period_end,
                    'canceled_at'            => optional($s->canceled_at)->toDateTimeString(),
                    'total_amount'           => $s->total_amount,
                    'currency'               => $s->currency,
                    'listing'                => $s->relationLoaded('listing') && $s->listing ? [
                        'id'   => $s->listing->id,
                        'name' => $s->listing->name ?? $s->listing->title ?? null,
                    ] : null,
                    'created_at'             => optional($s->created_at)->toDateTimeString(),
                ];
            })->values(),

            'reviews' => $user->reviews_api->map(function ($r) {
                return [
                    'id'         => $r->id,
                    'rating'     => $r->rating,
                    'review'     => $r->review,
                    'status'     => $r->status,
                    'created_at' => optional($r->created_at)->toDateTimeString(),
                    'video'      => $r->relationLoaded('video') && $r->video ? [
                        'id'    => $r->video->id,
                        'title' => $r->video->title,
                    ] : null,
                ];
            })->values(),
        ];
    }



    /**
     * Resend OTP to email.
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        /** @var ApiUser $user */
        $user = ApiUser::where('email', $request->email)->first();

        if ($user->is_verified) {
            return response()->json([
                'status' => true,
                'message' => 'Your email is already verified.'
            ], 200);
        }

        $this->issueAndSendOtp($user);

        return response()->json([
            'status' => true,
            'message' => 'A new verification code has been emailed to you.'
        ], 200);
    }

    /**
     * Verify OTP and mark user as verified.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        /** @var ApiUser $user */
        $user = ApiUser::where('email', $request->email)->first();

        if ($user->is_verified) {
            return response()->json([
                'status' => true,
                'message' => 'Your email is already verified.'
            ], 200);
        }

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json([
                'status' => false,
                'message' => 'No active verification code. Please request a new one.'
            ], 422);
        }

        if (Carbon::parse($user->otp_expires_at)->isPast()) {
            return response()->json([
                'status' => false,
                'message' => 'Verification code has expired. Please request a new one.'
            ], 422);
        }

        if ($request->otp !== $user->otp_code) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid verification code.'
            ], 422);
        }

        // Mark verified & clear otp
        $user->is_verified = true;
        $user->email_verified_at = now();
        $user->otp_code = null;
        $user->otp_expires_at = null;
        $user->save();

        // Optional: auto-login after verify
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                //'token' => $token,
                'user' => $user
            ]
        ], 200);
    }

    /**
     * Generate a 6-digit OTP, set expiry, and email it.
     */
    private function issueAndSendOtp(ApiUser $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->otp_code = $otp; // For higher security, you can store a hash instead.
        $user->otp_expires_at = now()->addMinutes($this->otpTtlMinutes);
        $user->save();

        Mail::to($user->email)->send(new VerifyOtpMail($user->name, $otp, $this->otpTtlMinutes));
    }

    /**
     * Logout user (invalidate token).
     */
    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out.'
        ], 200);
    }

    /**
     * Get authenticated user details.
     */
    public function me()
    {
        return response()->json([
            'status' => true,
            'message' => 'User profile retrieved successfully.',
            'data' => auth('api')->user()
        ], 200);
    }

/**
 * Step 1: Forgot Password (send OTP)
 */
// ...

public function forgotPassword(Request $request)
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $user = ApiUser::where('email', $request->email)->first();
    if (!$user) {
        return response()->json(['status' => false, 'message' => 'User not found.'], 404);
    }

    $otp = (string) random_int(100000, 999999);
    $user->otp_code = $otp;
    $user->otp_expires_at = now()->addMinutes($this->otpTtlMinutes);
    $user->save();

    Mail::to($user->email)->send(new ResetPasswordOtpMail($user->name, $otp, $this->otpTtlMinutes));

    return response()->json([
        'status' => true,
        'message' => 'A password reset code has been sent to your email.'
    ], 200);
}



/**
 * Step 2: Reset Password using OTP
 */
public function resetPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
        'otp' => 'required|digits:6',
        'password' => 'required|min:6|confirmed', // expect password + password_confirmation
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors()
        ], 422);
    }

    /** @var ApiUser $user */
    $user = ApiUser::where('email', $request->email)->first();

    if (!$user->otp_code || !$user->otp_expires_at) {
        return response()->json([
            'status' => false,
            'message' => 'No active OTP found. Please request a new one.'
        ], 422);
    }

    if (Carbon::parse($user->otp_expires_at)->isPast()) {
        return response()->json([
            'status' => false,
            'message' => 'OTP has expired. Please request a new one.'
        ], 422);
    }

    if ($request->otp !== $user->otp_code) {
        return response()->json([
            'status' => false,
            'message' => 'Invalid OTP.'
        ], 422);
    }

    // Update password and clear OTP
    $user->password = Hash::make($request->password);
    $user->otp_code = null;
    $user->otp_expires_at = null;
    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'Password has been reset successfully. You can now log in with your new password.',
    ], 200);
}
}