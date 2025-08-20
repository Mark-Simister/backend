<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
   // Ensure that only admin can access
    public function __construct()
    {
        
        // $this->middleware('auth'); 
        // $this->middleware('role:super_admin'); 
    }
    // Display the list of roles
    public function index()
    {
        $roles = Role::whereNotIn('name', ['user', 'super_admin'])->get(); // Excluding 'user' and 'super_admin'
        return view('admin.roles.index', compact('roles'));
    }

    // Show the form to create a new role
    public function create()
    {
        return view('admin.roles.create');
    }

    // Store a newly created role in storage
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            
        ]);
        
        $validated['guard_name'] = 'web';

        Role::create($validated);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    // Show the form for editing a role
    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    // Update the specified role in storage
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            
        ]);
        
        $validated['guard_name'] = 'web';

        $role->update($validated);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    // Remove the specified role from storage
    public function destroy(Role $role)
    {
        // You can prevent deleting roles that are required like 'super_admin' or 'user'
        if (in_array($role->name, ['user', 'super_admin'])) {
            return redirect()->route('admin.roles.index')->with('error', 'You cannot delete this role.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }

    public function editPermissions(Role $role)
    {
        // Here, fetch the available permissions and the current ones assigned to the role
        $permissions = Permission::all();  // Assuming you have a Permission model
        $rolePermissions = $role->permissions->pluck('id')->toArray(); // Assuming a many-to-many relationship

        return view('admin.roles.permissions', compact('role', 'permissions', 'rolePermissions'));
    }
    public function updatePermissions(Request $request, Role $role)
    {
        $role->permissions()->sync($request->permissions);  // Sync the permissions for the role

        return redirect()->route('admin.roles.index')->with('success', 'Permissions updated successfully.');
    }
}
