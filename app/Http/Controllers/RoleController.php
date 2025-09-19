<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role as SpatieRole;


class RoleController extends Controller
{
   // Ensure that only admin can access
    public function __construct()
    {
        
        // $this->middleware('auth'); 
        // $this->middleware('role:super_admin'); 
    }
    public function index()
    {
        $roles = Role::whereNotIn('name', ['user', 'super_admin'])->get(); // Excluding 'user' and 'super_admin'
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        return view('admin.roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            
        ]);
        
        $validated['guard_name'] = 'web';

        Role::create($validated);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }


    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            
        ]);
        
        $validated['guard_name'] = 'web';

        $role->update($validated);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {

        if (in_array($role->name, ['user', 'super_admin'])) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'You cannot delete this role.');
        }

        // Check if role is assigned to any user
        $assignedUsers = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($assignedUsers > 0) {
            return redirect()
                ->route('admin.roles.index')
                ->with('error', 'This role has users assigned. Please reassign or remove users before deleting.');
        }

        $role->delete();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }



    public function editPermissions(Role $role)
    {
     
        $permissions = Permission::all();  
        $rolePermissions = $role->permissions->pluck('id')->toArray(); 

        return view('admin.roles.permissions', compact('role', 'permissions', 'rolePermissions'));
    }
  
//     public function updatePermissions(Request $request, Role $role)
// {
    
//     $role->load('permissions');  

//     if ($role->permissions->isEmpty()) {
//         \Log::info('No permissions found for this role');
//     } else {
//         \Log::info('Permissions for the role: ', $role->permissions->toArray());
//     }

//     $permissions = Permission::find($request->permissions);
//     $role->syncPermissions($permissions);
//     // dd($permissions);

//     return redirect()->route('admin.roles.index')->with('success', 'Permissions updated successfully.');
// }
public function updatePermissions(Request $request, Role $role)
{
    // Load the role's permissions
    $role->load('permissions');  

    // Log the current permissions for debugging
    if ($role->permissions->isEmpty()) {
       // \Log::info('No permissions found for this role');
    } else {
       // \Log::info('Permissions for the role: ', $role->permissions->toArray());
    }

    // Validate the incoming permissions (assuming they are being passed by ID)
    $permissions = Permission::find($request->permissions);
    

    // Sync the role's permissions
    $role->syncPermissions($permissions);

    // Now sync the permissions with users who have this role
    $usersWithRole = $role->users; 

    // Ensure users are available before looping
    if ($usersWithRole->isEmpty()) {
       // \Log::info('No users found for this role.');
    } else {
        // Loop through each user and sync their permissions
        foreach ($usersWithRole as $user) {
            $user->syncPermissions($permissions);  // Sync the updated permissions to the user
        }
      //  \Log::info('Permissions synced with users.');
    }

    return redirect()->route('admin.roles.index')->with('success', 'Permissions updated successfully.');
}





}
