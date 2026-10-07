<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\PermissionGroupEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
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

    public function store(RoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(Permission::whereIn('name', $validated['permissions'])->get());

        AdminAudit::log('Created role '.$role->name, $role, ['permissions' => $validated['permissions']]);

        return to_route('admin.roles.index')->withMessage($role->name.' created successfully!');
    }

    public function update(RoleRequest $request, Role $role)
    {
        $validated = $request->validated();
        $before = $role->permissions->pluck('name')->sort()->values()->all();

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions(Permission::whereIn('name', $validated['permissions'])->get());

        $after = collect($validated['permissions'])->sort()->values()->all();
        AdminAudit::log('Updated role '.$role->name, $role, [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);

        return to_route('admin.roles.index')->withMessage($role->name.' updated successfully!');
    }

    public function delete(Request $request, Role $role)
    {
        if (RoleEnum::tryFrom($role->name)) {
            return back()->withMessage('Built-in roles cannot be deleted.', null, MessageTypeEnum::error);
        }

        if ($role->users()->exists()) {
            return back()->withMessage($role->name.' still has users assigned — remove them first.', null, MessageTypeEnum::error);
        }

        $name = $role->name;
        $role->delete();

        AdminAudit::log('Deleted role '.$name);

        return to_route('admin.roles.index')->withMessage($name.' has been deleted.');
    }
}
