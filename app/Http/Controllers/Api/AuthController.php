<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApiUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Role;

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

    /**
     * Authenticate a user and issue a JWT.
     * Blocks login until email is verified.
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials.'
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
            ], 410); // Gone
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
            // invalidate the token because we won’t let them in
            auth('api')->logout();

            return response()->json([
                'status' => false,
                'message' => 'Email not verified. We have sent (or re-sent) a verification code to your email.',
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'user' => $user
            ]
        ], 200);
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
}