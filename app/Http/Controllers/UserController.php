<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ApiUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|exists:roles,name',
        ]);

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
            'role' => 'required|string|exists:roles,name',
        ]);

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

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

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

    // GET /api/me/profile  (optional helper for current user)
    public function me_api(Request $request)
    {
        $user = $request->user(); // ApiUser via auth:api
        $user->load([
            'subscriptions_api' => fn ($q) => $q->with('listing')->latest(),
            'reviews_api'       => fn ($q) => $q->latest()->with(['video:id,title']),
            'roles',
        ]);

        $data = $this->shapeUser($user);

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

}
