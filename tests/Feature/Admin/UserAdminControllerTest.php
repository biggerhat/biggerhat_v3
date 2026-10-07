<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewUser, PermissionEnum::EditUser] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    foreach (RoleEnum::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([PermissionEnum::ViewUser->value, PermissionEnum::EditUser->value]);
});

it('assigns the Supporter role and a supporter_since date to a user', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $target->slug), [
            'roles' => [RoleEnum::Supporter->value],
            'supporter_since' => '2026-03-04',
        ])
        ->assertRedirect(route('admin.users.index'));

    $target->refresh();
    expect($target->isSupporter())->toBeTrue();
    expect($target->supporter_since->format('Y-m-d'))->toBe('2026-03-04');
});

it('removing the Supporter role from the roles array revokes it', function () {
    $target = User::factory()->create();
    $target->assignRole(RoleEnum::Supporter->value);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $target->slug), [
            'roles' => [],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->isSupporter())->toBeFalse();
});

it('rejects an invalid supporter_since value', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->postJson(route('admin.users.update', $target->slug), [
            'roles' => [RoleEnum::Supporter->value],
            'supporter_since' => 'not-a-date',
        ])
        ->assertStatus(422);
});

it('lets a super admin rename another user and updates their slug', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    $target = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.rename', $target->slug), ['name' => 'Brand New Name'])
        ->assertRedirect(route('admin.users.edit', 'brand-new-name'));

    $target->refresh();
    expect($target->name)->toBe('Brand New Name');
    expect($target->slug)->toBe('brand-new-name');
});

it('forbids a non-super-admin with edit_user from renaming a user', function () {
    $target = User::factory()->create(['name' => 'Keep Me']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.rename', $target->slug), ['name' => 'Changed'])
        ->assertForbidden();

    expect($target->fresh()->name)->toBe('Keep Me');
});

it('rejects renaming to a taken name or a name whose slug collides', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    User::factory()->create(['name' => 'Taken Name']);
    $target = User::factory()->create(['name' => 'Someone Else']);

    foreach (['Taken Name', 'taken-name', '!!!', ''] as $bad) {
        $this->actingAs($superAdmin)
            ->post(route('admin.users.rename', $target->slug), ['name' => $bad])
            ->assertSessionHasErrors('name');
    }

    expect($target->fresh()->name)->toBe('Someone Else');
});

it('allows a super admin to submit a user\'s unchanged name without a uniqueness error', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    $target = User::factory()->create(['name' => 'Same Name']);

    $this->actingAs($superAdmin)
        ->post(route('admin.users.rename', $target->slug), ['name' => 'Same Name'])
        ->assertSessionHasNoErrors();
});

it('blocks a non-super-admin from granting the super_admin role', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $target->slug), ['roles' => [RoleEnum::SuperAdmin->value]])
        ->assertSessionHasErrors('roles');

    expect($target->fresh()->hasRole(RoleEnum::SuperAdmin->value))->toBeFalse();
});

it('blocks a non-super-admin from stripping the super_admin role', function () {
    $target = User::factory()->create();
    $target->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $target->slug), ['roles' => []])
        ->assertSessionHasErrors('roles');

    expect($target->fresh()->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
});

it('lets a super admin grant super_admin and records an audit entry', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);
    $superAdmin->givePermissionTo(PermissionEnum::EditUser->value);
    $target = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.users.update', $target->slug), ['roles' => [RoleEnum::SuperAdmin->value]])
        ->assertRedirect(route('admin.users.index'));

    expect($target->fresh()->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Changed roles%')->exists())->toBeTrue();
});

it('rejects unknown role names', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.users.update', $target->slug), ['roles' => ['not_a_real_role']])
        ->assertSessionHasErrors('roles.0');
});

it('refuses to delete your own account', function () {
    Permission::firstOrCreate(['name' => PermissionEnum::DeleteUser->value, 'guard_name' => 'web']);
    $this->admin->givePermissionTo(PermissionEnum::DeleteUser->value);

    $this->actingAs($this->admin)
        ->post(route('admin.users.delete', $this->admin->slug))
        ->assertRedirect();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('refuses to delete a super admin', function () {
    Permission::firstOrCreate(['name' => PermissionEnum::DeleteUser->value, 'guard_name' => 'web']);
    $this->admin->givePermissionTo(PermissionEnum::DeleteUser->value);
    $target = User::factory()->create();
    $target->assignRole(RoleEnum::SuperAdmin->value);

    $this->actingAs($this->admin)->post(route('admin.users.delete', $target->slug))->assertRedirect();

    expect(User::find($target->id))->not->toBeNull();
});

it('deletes a regular user and audits it', function () {
    Permission::firstOrCreate(['name' => PermissionEnum::DeleteUser->value, 'guard_name' => 'web']);
    $this->admin->givePermissionTo(PermissionEnum::DeleteUser->value);
    $target = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.delete', $target->slug))->assertRedirect(route('admin.users.index'));

    expect(User::find($target->id))->toBeNull();
    expect(Activity::where('log_name', 'admin')->where('description', 'like', 'Deleted user%')->exists())->toBeTrue();
});

it('denies delete without the delete_user permission', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.users.delete', $target->slug))->assertForbidden();
});
