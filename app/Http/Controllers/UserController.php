<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->latest()->get();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::where('name', '!=', 'super_admin')->pluck('name', 'name');
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

        $user = User::create([
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

    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = Role::where('name', '!=', 'super_admin')->pluck('name', 'name');
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
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

    public function destroy(User $user)
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

}
