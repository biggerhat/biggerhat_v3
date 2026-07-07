<?php

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\User;
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
