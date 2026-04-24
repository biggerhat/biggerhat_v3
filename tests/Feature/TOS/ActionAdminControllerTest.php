<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\ActionTypeEnum;
use App\Models\TOS\Action;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosAction, PermissionEnum::EditTosAction, PermissionEnum::DeleteTosAction] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');
});

it('index denies users without view_tos_action', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('admin.tos.actions.index'))->assertForbidden();
});

it('store creates an Action with multiple types', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.actions.store'), [
        'name' => 'Slash',
        'types' => [ActionTypeEnum::Melee->value, ActionTypeEnum::Magic->value],
        'av' => 6,
        'strength' => 2,
    ])->assertRedirect(route('admin.tos.actions.index'));

    $a = Action::where('name', 'Slash')->first();
    expect($a)->not->toBeNull()
        ->and($a->slug)->toStartWith('slash-')
        ->and($a->av)->toBe(6)
        ->and($a->fresh()->typeLinks->pluck('type'))->toContain(ActionTypeEnum::Melee, ActionTypeEnum::Magic);
});

it('rejects an Action with no types', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.actions.store'), [
        'name' => 'Invalid',
        'types' => [],
    ])->assertStatus(422)->assertJsonValidationErrors(['types']);
});

it('rejects missing name', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.actions.store'), [
        'types' => [ActionTypeEnum::Melee->value],
    ])->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('update modifies an Action and replaces its types', function () {
    $a = Action::factory()->melee()->create(['name' => 'Original']);

    $this->actingAs($this->admin)->post(route('admin.tos.actions.update', $a->slug), [
        'name' => 'Renamed',
        'types' => [ActionTypeEnum::Magic->value],
    ])->assertRedirect();

    $a->refresh()->load('typeLinks');
    expect($a->name)->toBe('Renamed')
        ->and($a->typeLinks->pluck('type'))->toContain(ActionTypeEnum::Magic)
        ->and($a->typeLinks->pluck('type'))->not->toContain(ActionTypeEnum::Melee);
});

it('delete removes the Action and cascades typeLinks', function () {
    $a = Action::factory()->withTypes(ActionTypeEnum::Magic)->create();
    $actionId = $a->id;

    $this->actingAs($this->admin)->post(route('admin.tos.actions.delete', $a->slug))->assertRedirect();

    expect(Action::find($actionId))->toBeNull()
        ->and(\DB::table('tos_action_types')->where('action_id', $actionId)->count())->toBe(0);
});

it('store denies users without edit_tos_action', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAction->value);

    $this->actingAs($viewer)->post(route('admin.tos.actions.store'), [
        'name' => 'Blocked',
        'types' => [ActionTypeEnum::Melee->value],
    ])->assertForbidden();

    expect(Action::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_action', function () {
    $a = Action::factory()->melee()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAction->value);

    $this->actingAs($viewer)->post(route('admin.tos.actions.update', $a->slug), [
        'name' => 'Hijacked',
        'types' => [ActionTypeEnum::Melee->value],
    ])->assertForbidden();

    expect($a->fresh()->name)->toBe('Original');
});

it('delete denies users without delete_tos_action', function () {
    $a = Action::factory()->melee()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosAction->value);

    $this->actingAs($viewer)->post(route('admin.tos.actions.delete', $a->slug))->assertForbidden();

    expect(Action::find($a->id))->not->toBeNull();
});
