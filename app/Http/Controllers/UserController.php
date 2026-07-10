<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {

    $role = Role::where('name', 'user')
    ->where('guard_name', 'api')
    ->first();


    $users = ApiUser::whereHas('roles', function ($q) {
        $q->where('name', 'user')
          ->where('guard_name', 'api');
    })
    ->latest()
    ->get();


        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::where('name',  'user')->where('guard_name','api')->pluck('name', 'name');
        // dd($roles);
        return view('admin.users.create', compact('roles'));
    }

    /**
     * A role may be assigned only if it exists on the TARGET model's guard, and only a
     * super_admin may grant super_admin.
     *
     * `exists:roles,name` was never a control. `super_admin` exists - on the `web` guard -
     * so it validated, and `$user->update(['role' => ...])` persisted
     * `users.role = 'super_admin'` before `assignRole()` failed on the guard mismatch.
     * These routes manage ApiUser records, which live on the `api` guard.
     */
    private function assertMayAssignRole(string $role, string $guard): void
    {
        $actor = auth()->user();
        abort_unless($actor, 403);

        abort_unless(
            Role::where('name', $role)->where('guard_name', $guard)->exists(),
            403,
            "Role [{$role}] does not exist on the [{$guard}] guard."
        );

        abort_if(
            $role === 'super_admin' && ! $actor->hasRole('super_admin'),
            403,
            'Only a super_admin may grant super_admin.'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
        ]);

        // Authorize BEFORE anything is written. The old code persisted the role column and
        // only then discovered it could not attach the role.
        $this->assertMayAssignRole($request->role, 'api');

        $user = ApiUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_verified' => true,
        ]);

        $user->assignRole($request->role);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function show(ApiUser $user)
    {
       
        $user->load([
        'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
        'reviews_api'       => fn ($q) => $q->latest()->with(['video:id,title']),
        'roles',
    ]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(ApiUser $user)
    {
        $roles = Role::where('name', '!=', 'super_admin')->pluck('name', 'name');
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, ApiUser $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            // 'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6|confirmed',
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'api')],
        ]);

        $this->assertMayAssignRole($request->role, 'api');

        $user->update([
            'name' => $request->name,
            // 'email'    => $request->email,
            'phone' => $request->phone,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
            'role' => $request->role,
        ]);

        $user->syncRoles([$request->role]);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(ApiUser $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }


    // sub-admin creation

    // Display form to create sub-admin
    public function create_sub_admin()
    {
        // $roles = Role::all();  
        $roles = Role::whereNotIn('name', ['user', 'super_admin'])->get();
        return view('admin.sub_admins.create', compact('roles'));
    }

    // Store sub-admin data
    public function store_sub_admin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',
        ]);
        // dd($request->all());

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        // `role` is guarded on User and no longer mass-assignable. This route sits inside
        // the `role:super_admin` group, so the caller is already authorized to set it.
        $user->forceFill(['role' => 'sub_admin'])->save();

        // Assign the selected role to the user
        $user->assignRole($validated['role']);

        return redirect()->route('admin.sub_admins.index')->with('success', 'Sub-admin created successfully.');
    }

    // Edit existing user (sub-admin)
    public function edit_sub_admin(User $user)
    {
        // $roles = Role::all();
         $roles = Role::whereNotIn('name', ['user', 'super_admin'])->get();
        return view('admin.sub_admins.edit', compact('user', 'roles'));
    }

    public function update_sub_admin(Request $request, User $user)
    {
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed', // Validate password only if it's provided
        ]);

        
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        
        if ($request->filled('password')) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.sub_admins.index')->with('success', 'Sub-admin updated successfully.');
    }


    // List users
    public function index_sub_admin()
    {
        $users = User::whereHas('roles', function($query) {
            $query->whereNotIn('name', ['user', 'super_admin']);
        })->get();
        return view('admin.sub_admins.index', compact('users'));
    }


    public function destroy_sub_admin(User $user)
    {
        if ($user->hasRole('sub_admin')) {
            $user->delete(); 

            return redirect()->route('admin.sub_admins.index')->with('success', 'Sub-admin deleted successfully.');
        }

    
        return redirect()->route('admin.sub_admins.index')->with('error', 'User is not a sub-admin.');
    }

    // Display form to assign roles to sub-admins
    public function assignRoles(User $user)
    {
        // Fetch all users and available roles
        $users = User::whereHas('roles', function($query) {
            $query->where('name', 'sub_admin');
        })->get(); 

        $roles = Role::whereNotIn('name', ['user', 'super_admin'])->get();

        return view('admin.sub_admins.assign_roles', compact('users','user', 'roles'));
    }
    public function updateRole(Request $request, User $user)
    {
        // Sync the roles with the user (assign the roles)
        $user->syncRoles($request->roles);  // Assuming you are using the `Spatie\Permission` package
        return redirect()->route('admin.sub_admins.index')->with('success', 'Roles assigned successfully.');
    }


    // Update roles for a selected sub-admin
    public function updateRoles(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        // Get the user
        $user = User::findOrFail($validated['user_id']);

        // Sync roles for the user (this will remove any previous roles and add the new ones)
        $user->syncRoles($validated['roles']);

        return redirect()->route('admin.sub_admins.index')->with('success', 'Roles assigned successfully.');
    }

    public function toggleBlock(Request $request, User $user)
{
    // Example authorization check (optional):
    // $this->authorize('update', $user);

    $user->is_blocked = ! $user->is_blocked;
    $user->save();

    return response()->json([
        'success'    => true,
        'is_blocked' => $user->is_blocked,
        'message'    => $user->is_blocked ? 'User blocked' : 'User unblocked',
    ]);
}


    // Api's

    // GET /api/users/{user}
     public function show_api($id)
    {
        $user = ApiUser::query()
            ->with([
                'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
                'reviews_api'       => fn ($q) => $q->latest()->with(['video:id,title']),
                'roles',
            ])
            ->find($id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found.',
            ], 404);
        }

        $data = $this->shapeUser($user);

        return response()->json([
            'status'  => true,
            'message' => 'User details fetched successfully',
            'data'    => $data,
        ], 200);
    }

//     public function me_api(Request $request)
// {
//     $user = $request->user(); // ApiUser via auth:api
//     $user->load([
//         'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
//         'roles',
//     ]);

//     $data = $this->shapeUser($user);

//     // Add phone and profile image URL
//     $data['phone'] = $user->phone;
//     $data['profile_image'] = $user->profile_image ? asset($user->profile_image) : null;

//     return response()->json([
//         'status'  => true,
//         'message' => 'Profile fetched successfully',
//         'data'    => $data,
//     ], 200);
// }
public function me_api(Request $request)
{
    $user = $request->user(); // Authenticated API user

    // Load relationships
    $user->load([
        'subscriptions_api' => fn($q) => $q->with('listing')->latest(),
        'roles',
    ]);

    // Shape base user data
    $data = $this->shapeUser($user);

    // Handle subscriptions and their currencies
    if (!empty($data['subscriptions']) && count($data['subscriptions']) > 0) {
        // Collect unique currency codes (in uppercase)
        $currencyCodes = collect($data['subscriptions'])
            ->pluck('currency')
            ->filter()
            ->map(fn($code) => strtoupper($code))
            ->unique()
            ->values();

        // Fetch all currency details at once
        $currencies = \App\Models\Currency::whereIn('currency_code', $currencyCodes)
            ->select('id', 'currency_code', 'currency_name', 'currency_symbol', 'created_at', 'updated_at')
            ->get()
            ->keyBy(fn($c) => strtoupper($c->currency_code));

        // Attach currency info to each subscription
        $data['subscriptions'] = collect($data['subscriptions'])->map(function ($sub) use ($currencies) {
            $code = strtoupper($sub['currency']);
            if ($currencies->has($code)) {
                $currency = $currencies->get($code);
                $sub['currency_symbol'] = $currency->currency_symbol;
                $sub['currency_details'] = $currency;
            } else {
                $sub['currency_symbol'] = null;
                $sub['currency_details'] = null;
            }
            return $sub;
        })->values();
    }

    // Add phone and profile image URL
    $data['phone'] = $user->phone;
    $data['profile_image'] = $user->profile_image ? asset($user->profile_image) : null;

    return response()->json([
        'status'  => true,
        'message' => 'Profile fetched successfully',
        'data'    => $data,
    ], 200);
}




    /**
     * Normalize the JSON shape (kept minimal & readable).
     */
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

            // 'reviews' => $user->reviews_api->map(function ($r) {
            //     return [
            //         'id'         => $r->id,
            //         'rating'     => $r->rating,
            //         'review'     => $r->review,
            //         'status'     => $r->status,
            //         'created_at' => optional($r->created_at)->toDateTimeString(),
            //         'video'      => $r->relationLoaded('video') && $r->video ? [
            //             'id'    => $r->video->id,
            //             'title' => $r->video->title,
            //         ] : null,
            //     ];
            // })->values(),
        ];
    }

    public function update_api(Request $request)
{
    $user = $request->user();

    // Validate input
    $validated = $request->validate([
        'name'          => ['sometimes', 'string', 'max:255'],
        'phone'         => ['sometimes', 'nullable', 'string', 'max:30'],
        'password'      => ['sometimes', 'nullable', 'confirmed', 'min:8'],
        'profile_image' => ['sometimes', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], 
    ]);

    // Ensure user uploads directory exists
    $destination = public_path('users');
    if (!File::exists($destination)) {
        File::makeDirectory($destination, 0755, true);
    }

    // Check if email changed
    $emailChanged = array_key_exists('email', $validated) && $validated['email'] !== $user->email;

    // Update user fields if present
    if (array_key_exists('name', $validated)) {
        $user->name = $validated['name'];
    }

    if (array_key_exists('phone', $validated)) {
        // Can be null to clear existing phone
        $user->phone = $validated['phone'];
    }

    if (array_key_exists('password', $validated) && $validated['password']) {
        $user->password = Hash::make($validated['password']);
    }

    if ($emailChanged) {
        $user->email_verified_at = null;
    }

    // Handle profile image upload
    if ($request->hasFile('profile_image')) {
        $file = $request->file('profile_image');

        // Delete old image if exists
        if ($user->profile_image) {
            $oldPath = public_path($user->profile_image); 
            if (File::exists($oldPath) && str_starts_with(realpath($oldPath), realpath($destination))) {
                @File::delete($oldPath);
            }
        }

        $filename = 'user_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $file->move($destination, $filename);
        $user->profile_image = 'users/' . $filename;
    }

    $user->save();

    $imageUrl = $user->profile_image ? asset($user->profile_image) : null;

    return response()->json([
        'message' => 'Profile updated successfully.',
        'data' => [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'email_verified_at' => $user->email_verified_at,
            'role'              => $user->role,
            'profile_image'     => $imageUrl,
        ],
    ]);
}




//     public function me_api(Request $request)
// {
//     $user = $request->user(); // ApiUser via auth:api
//     $user->load([
//         'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
//         'reviews_api'       => fn ($q) => $q->latest()->with(['video:id,title']),
//         'roles',
//     ]);

//     $data = $this->shapeUser($user);

//     return response()->json([
//         'status'  => true,
//         'message' => 'Profile fetched successfully',
//         'data'    => $data,
//     ], 200);
// }

// /**
//  * Normalize the JSON shape and categorize subscriptions:
//  * - current_subscription: covers "now" (trial or paid)
//  * - expired_subscriptions: ended before now
//  * - future_subscriptions: start after now
//  */
// private function shapeUser(ApiUser $user): array
// {
//     $now = now();

//     /**
//      * Helpers to reason about a subscription timeline.
//      */
//     $isCanceled = static function ($s): bool {
//         // Treat explicit canceled status or canceled_at set as canceled
//         return ($s->subscription_status === 'canceled') || !is_null($s->canceled_at);
//     };

//     $periodStart = static function ($s) {
//         // Prefer trial start when present, otherwise paid start
//         return $s->trial_start_date ?? $s->subscription_start_date;
//     };

//     $periodEnd = static function ($s) {
//         // Prefer trial end when it's in the future; otherwise paid end
//         // (If both exist, we consider the furthest that still applies)
//         // Practically: membership is active if NOW <= (trial_end or paid_end)
//         return $s->trial_end_date ?: $s->subscription_end_date;
//     };

//     $isActiveNow = static function ($s, $now, $isCanceled, $periodStart, $periodEnd): bool {
//         // Must be marked active or trialing, not canceled, and within dates.
//         $statusOkay  = in_array($s->subscription_status, ['active','trialing'], true);
//         if (!$statusOkay || $isCanceled($s)) {
//             return false;
//         }

//         $start = $periodStart($s);
//         $end   = $periodEnd($s);

//         // If we have both, require: start <= now <= end
//         // If start is null, just require now <= end
//         // If end is null, assume open-ended after start
//         $afterStart = $start ? $now->gte($start) : true;
//         $beforeEnd  = $end   ? $now->lte($end)   : true;

//         return $afterStart && $beforeEnd;
//     };

//     $isFuture = static function ($s, $now, $periodStart): bool {
//         $start = $periodStart($s);
//         return $start && $now->lt($start);
//     };

//     $isExpired = static function ($s, $now, $periodEnd): bool {
//         $end = $periodEnd($s);
//         // If we have an end date and it's in the past, it's expired
//         return $end && $now->gt($end);
//     };

//     // Work with a copy sorted by the most relevant effective start descending
//     $subs = $user->subscriptions_api
//         ->sortByDesc(function ($s) use ($periodStart) {
//             return optional($periodStart($s))->timestamp ?? -INF;
//         })
//         ->values();

//     // Find the single "current" subscription (the one covering NOW)
//     $current = $subs->first(function ($s) use ($now, $isCanceled, $periodStart, $periodEnd, $isActiveNow) {
//         return $isActiveNow($s, $now, $isCanceled, $periodStart, $periodEnd);
//     });

//     // Future = start date in the future (regardless of status label)
//     $future = $subs->filter(function ($s) use ($now, $periodStart, $isFuture) {
//         return $isFuture($s, $now, $periodStart);
//     })->values();

//     // Expired = ended in the past
//     $expired = $subs->filter(function ($s) use ($now, $periodEnd, $isExpired) {
//         return $isExpired($s, $now, $periodEnd);
//     })->values();

//     // Flag
//     $hasActiveSubscription = (bool) $current;

//     // Shape a single subscription into your compact JSON
//     $shapeOne = function ($s) {
//         return [
//             'id'                       => $s->id,
//             'subscription_name'        => $s->subscription_name,
//             'subscription_period'      => $s->subscription_period,
//             'billing_cycle'            => $s->billing_cycle,
//             'subscription_start_date'  => optional($s->subscription_start_date)->toDateString(),
//             'subscription_end_date'    => optional($s->subscription_end_date)->toDateString(),
//             'trial_start_date'         => optional($s->trial_start_date)->toDateString(),
//             'trial_end_date'           => optional($s->trial_end_date)->toDateString(),
//             'payment_status'           => $s->payment_status,
//             'subscription_status'      => $s->subscription_status,
//             'auto_renew'               => (bool) $s->auto_renew,
//             'cancel_at_period_end'     => (bool) $s->cancel_at_period_end,
//             'canceled_at'              => optional($s->canceled_at)->toDateTimeString(),
//             'total_amount'             => $s->total_amount,
//             'currency'                 => $s->currency,
//             'listing'                  => $s->relationLoaded('listing') && $s->listing ? [
//                 'id'   => $s->listing->id,
//                 'name' => $s->listing->name ?? $s->listing->title ?? null,
//             ] : null,
//             'created_at'               => optional($s->created_at)->toDateTimeString(),
//         ];
//     };

//     return [
//         'id'    => $user->id,
//         'name'  => $user->name,
//         'email' => $user->email,
//         'roles' => $user->roles->pluck('name')->values(),

//         'has_active_subscription' => $hasActiveSubscription,

//         // The one covering *today* (null if none)
//         'current_subscription'    => $current ? $shapeOne($current) : null,

//         // Already ended
//         'expired_subscriptions'   => $expired->map($shapeOne)->values(),

//         // Purchased but not started yet
//         'future_subscriptions'    => $future->map($shapeOne)->values(),

//         // (Optional) Keep your original flattened list (unchanged)
//         'subscriptions' => $user->subscriptions_api->map($shapeOne)->values(),

//         // Reviews as you had before
//         'reviews' => $user->reviews_api->map(function ($r) {
//             return [
//                 'id'         => $r->id,
//                 'rating'     => $r->rating,
//                 'review'     => $r->review,
//                 'status'     => $r->status,
//                 'created_at' => optional($r->created_at)->toDateTimeString(),
//                 'video'      => $r->relationLoaded('video') && $r->video ? [
//                     'id'    => $r->video->id,
//                     'title' => $r->video->title,
//                 ] : null,
//             ];
//         })->values(),
//     ];
// }


}
