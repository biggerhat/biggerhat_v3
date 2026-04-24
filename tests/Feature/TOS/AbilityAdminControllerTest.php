<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\Ability;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosAbility, PermissionEnum::EditTosAbility, PermissionEnum::DeleteTosAbility] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');
});

it('index denies users without view_tos_ability', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('admin.tos.abilities.index'))->assertForbidden();
});

it('store creates a general Ability with a generated slug', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.abilities.store'), [
        'name' => 'Fast',
        'body' => 'This model gains +1 to Walk.',
        'is_general' => true,
    ])->assertRedirect(route('admin.tos.abilities.index'));

    $a = Ability::where('name', 'Fast')->first();
    expect($a)->not->toBeNull()
        ->and($a->is_general)->toBeTrue()
        ->and($a->slug)->toStartWith('fast-')
        ->and($a->allegiance_id)->toBeNull();
});

it('store creates a non-general Ability (is_general = false)', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.abilities.store'), [
        'name' => 'Disciplined',
        'is_general' => false,
    ])->assertRedirect();

    $a = Ability::where('name', 'Disciplined')->first();
    expect($a)->not->toBeNull()->and($a->is_general)->toBeFalse();
});

it('rejects missing name', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.abilities.store'), [
        'is_general' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('update modifies an Ability', function () {
    $a = Ability::factory()->general()->create(['name' => 'Original']);

    $this->actingAs($this->admin)->post(route('admin.tos.abilities.update', $a->slug), [
        'name' => 'Renamed',
        'is_general' => true,
    ])->assertRedirect();

    expect($a->fresh()->name)->toBe('Renamed');
});

it('delete removes the ability', function () {
    $a = Ability::factory()->general()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.abilities.delete', $a->slug))->assertRedirect();

    expect(Ability::find($a->id))->toBeNull();
});

it('store denies users without edit_tos_ability', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAbility->value);

    $this->actingAs($viewer)->post(route('admin.tos.abilities.store'), [
        'name' => 'Blocked',
        'is_general' => true,
    ])->assertForbidden();

    expect(Ability::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_ability', function () {
    $a = Ability::factory()->general()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAbility->value);

    $this->actingAs($viewer)->post(route('admin.tos.abilities.update', $a->slug), [
        'name' => 'Hijacked',
        'is_general' => true,
    ])->assertForbidden();

    expect($a->fresh()->name)->toBe('Original');
});

it('delete denies users without delete_tos_ability', function () {
    $a = Ability::factory()->general()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAbility->value);

    $this->actingAs($viewer)->post(route('admin.tos.abilities.delete', $a->slug))->assertForbidden();

    expect(Ability::find($a->id))->not->toBeNull();
});
