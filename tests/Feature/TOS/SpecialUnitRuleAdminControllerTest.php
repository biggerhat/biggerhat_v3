<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\SpecialUnitRule;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosSpecialUnitRule, PermissionEnum::EditTosSpecialUnitRule, PermissionEnum::DeleteTosSpecialUnitRule] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');
});

it('index denies users without view_tos_special_unit_rule', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('admin.tos.special_rules.index'))->assertForbidden();
});

it('store creates a Special Unit Rule with a deterministic slug', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.special_rules.store'), [
        'name' => 'Titan',
        'description' => 'A Titan is immense.',
    ])->assertRedirect(route('admin.tos.special_rules.index'));

    $r = SpecialUnitRule::where('name', 'Titan')->first();
    // Canonical rule names don't need a random suffix.
    expect($r)->not->toBeNull()->and($r->slug)->toBe('titan');
});

it('rejects missing name', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.special_rules.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('update modifies a Special Unit Rule', function () {
    $r = SpecialUnitRule::factory()->create(['name' => 'Original']);

    $this->actingAs($this->admin)->post(route('admin.tos.special_rules.update', $r->slug), [
        'name' => 'Renamed',
        'description' => 'Updated description',
    ])->assertRedirect();

    $r->refresh();
    expect($r->name)->toBe('Renamed')->and($r->description)->toBe('Updated description');
});

it('delete removes the rule', function () {
    $r = SpecialUnitRule::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.special_rules.delete', $r->slug))->assertRedirect();

    expect(SpecialUnitRule::find($r->id))->toBeNull();
});

it('store denies users without edit_tos_special_unit_rule', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosSpecialUnitRule->value);

    $this->actingAs($viewer)->post(route('admin.tos.special_rules.store'), [
        'name' => 'Blocked',
    ])->assertForbidden();

    expect(SpecialUnitRule::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_special_unit_rule', function () {
    $r = SpecialUnitRule::factory()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosSpecialUnitRule->value);

    $this->actingAs($viewer)->post(route('admin.tos.special_rules.update', $r->slug), [
        'name' => 'Hijacked',
    ])->assertForbidden();

    expect($r->fresh()->name)->toBe('Original');
});

it('delete denies users without delete_tos_special_unit_rule', function () {
    $r = SpecialUnitRule::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosSpecialUnitRule->value);

    $this->actingAs($viewer)->post(route('admin.tos.special_rules.delete', $r->slug))->assertForbidden();

    expect(SpecialUnitRule::find($r->id))->not->toBeNull();
});
