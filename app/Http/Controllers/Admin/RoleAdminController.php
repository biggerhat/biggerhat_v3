<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionEnum;
use App\Enums\PermissionGroupEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Roles/Index', [
            'roles' => Role::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $permissions = [];
        foreach (PermissionGroupEnum::cases() as $permissionGroup) {
            $permissions[$permissionGroup->label()] = PermissionEnum::getPermissionsByGroup($permissionGroup);
        }

        return inertia('Admin/Roles/RoleForm', [
            'permissions' => $permissions,
        ]);
    }

    public function edit(Request $request, Role $role)
    {
        $permissions = [];
        foreach (PermissionGroupEnum::cases() as $permissionGroup) {
            $permissions[$permissionGroup->label()] = PermissionEnum::getPermissionsByGroup($permissionGroup);
        }

        return inertia('Admin/Roles/RoleForm', [
            'role' => $role->loadMissing('permissions'),
            'checked_permissions' => $role->permissions->map(function ($permission) {
                /** @phpstan-ignore property.notFound */
                return $permission->name;
            }),
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')],
            'permissions' => ['required', 'array'],
        ]);

        $permissions = Permission::whereIn('name', $request->permissions)->get();

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        return to_route('admin.roles.index')->withMessage($role->name.' created successfully!');
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => ['required', 'array'],
        ]);

        $permissions = Permission::whereIn('name', $validated['permissions'])->get();
        unset($validated['permissions']);

        $role->update($validated);
        $role->syncPermissions($permissions);

        return to_route('admin.roles.index')->withMessage($role->name.' updated successfully!');
    }

    public function delete(Request $request, Role $role)
    {
        $name = $role->name;

        $role->delete();

        return to_route('admin.roles.index')->withMessage($name.' has been deleted.');
    }
}
