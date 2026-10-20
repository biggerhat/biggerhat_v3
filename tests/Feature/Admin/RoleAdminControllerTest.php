<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    foreach (RoleEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo([
        PermissionEnum::ViewRole->value,
        PermissionEnum::AddRole->value,
        PermissionEnum::EditRole->value,
        PermissionEnum::DeleteRole->value,
        PermissionEnum::ViewUser->value,
    ]);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    // Mirrors PermissionSeeder, which syncs every permission to super_admin.
    Role::findByName(RoleEnum::SuperAdmin->value)->syncPermissions(Permission::all());
});

it('gates role routes by permission', function () {
    $nobody = User::factory()->create();
    $role = Role::create(['name' => 'qa_tester', 'guard_name' => 'web']);

    $this->actingAs($nobody)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.roles.store'), [])->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.roles.update', $role), [])->assertForbidden();
    $this->actingAs($nobody)->post(route('admin.roles.delete', $role))->assertForbidden();
});

it('creates a role with permissions the actor holds and audits it', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.roles.store'), [
            'name' => 'qa_tester',
            'permissions' => [PermissionEnum::ViewUser->value],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('qa_tester');
    expect($role->hasPermissionTo(PermissionEnum::ViewUser->value))->toBeTrue();
    expect(Activity::where('log_name', 'admin')->where('description', 'Created role qa_tester')->exists())->toBeTrue();
});

it('rejects granting a permission the actor does not hold', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.roles.store'), [
            'name' => 'sneaky',
            'permissions' => [PermissionEnum::DeleteUser->value],
        ])
        ->assertSessionHasErrors('permissions');

    expect(Role::where('name', 'sneaky')->exists())->toBeFalse();
});

it('lets a super admin grant any permission', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), [
            'name' => 'power_role',
            'permissions' => [PermissionEnum::DeleteUser->value],
        ])
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::findByName('power_role')->hasPermissionTo(PermissionEnum::DeleteUser->value))->toBeTrue();
});

it('rejects unknown permission names and duplicate role names', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), ['name' => 'x_role', 'permissions' => ['nope']])
        ->assertSessionHasErrors('permissions.0');

    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.store'), ['name' => RoleEnum::Supporter->value, 'permissions' => []])
        ->assertSessionHasErrors('name');
});

it('refuses to rename a built-in role', function () {
    $role = Role::findByName(RoleEnum::Supporter->value);

    $this->actingAs($this->superAdmin)
        ->post(route('admin.roles.update', $role), ['name' => 'renamed', 'permissions' => []])
        ->assertSessionHasErrors('name');

    expect(Role::where('name', RoleEnum::Supporter->value)->exists())->toBeTrue();
});

it('forbids a non-super-admin from editing the super_admin role', function () {
    $role = Role::findByName(RoleEnum::SuperAdmin->value);

    $this->actingAs($this->manager)
        ->post(route('admin.roles.update', $role), [
            'name' => RoleEnum::SuperAdmin->value,
            'permissions' => [PermissionEnum::ViewRole->value],
        ])
        ->assertForbidden();
});

it('updates a custom role and records the permission diff', function () {
    $role = Role::create(['name' => 'qa_tester', 'guard_name' => 'web']);
    $role->givePermissionTo(PermissionEnum::ViewRole->value);

    $this->actingAs($this->manager)
        ->post(route('admin.roles.update', $role), [
            'name' => 'qa_tester',
            'permissions' => [PermissionEnum::ViewUser->value],
        ])
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->hasPermissionTo(PermissionEnum::ViewUser->value))->toBeTrue();
    expect($role->fresh()->hasPermissionTo(PermissionEnum::ViewRole->value))->toBeFalse();

    $entry = Activity::where('log_name', 'admin')->where('description', 'Updated role qa_tester')->first();
    expect($entry->properties['added'])->toBe([PermissionEnum::ViewUser->value]);
    expect($entry->properties['removed'])->toBe([PermissionEnum::ViewRole->value]);
});

it('refuses to delete built-in roles and roles that still have users', function () {
    $this->actingAs($this->manager)
        ->post(route('admin.roles.delete', Role::findByName(RoleEnum::SuperAdmin->value)))
        ->assertRedirect();
    expect(Role::where('name', RoleEnum::SuperAdmin->value)->exists())->toBeTrue();

    $custom = Role::create(['name' => 'qa_tester', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($custom);

    $this->actingAs($this->manager)->post(route('admin.roles.delete', $custom))->assertRedirect();
    expect(Role::where('name', 'qa_tester')->exists())->toBeTrue();
});

it('deletes an unused custom role', function () {
    $custom = Role::create(['name' => 'qa_tester', 'guard_name' => 'web']);

    $this->actingAs($this->manager)
        ->post(route('admin.roles.delete', $custom))
        ->assertRedirect(route('admin.roles.index'));

    expect(Role::where('name', 'qa_tester')->exists())->toBeFalse();
    expect(Activity::where('log_name', 'admin')->where('description', 'Deleted role qa_tester')->exists())->toBeTrue();
});
