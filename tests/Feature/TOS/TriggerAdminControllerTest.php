<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\Action;
use App\Models\TOS\Trigger;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosTrigger, PermissionEnum::EditTosTrigger, PermissionEnum::DeleteTosTrigger] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');
});

it('store accepts a suit-driven trigger', function () {
    $action = Action::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.triggers.store'), [
        'action_id' => $action->id,
        'name' => 'Critical',
        'suits' => 'R',
    ])->assertRedirect();

    $t = Trigger::where('name', 'Critical')->first();
    expect($t->suits)->toBe('R')->and($t->margin_cost)->toBeNull();
});

it('store accepts a margin-driven trigger', function () {
    $action = Action::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.triggers.store'), [
        'action_id' => $action->id,
        'name' => 'Overkill',
        'margin_cost' => 5,
    ])->assertRedirect();

    $t = Trigger::where('name', 'Overkill')->first();
    expect($t->margin_cost)->toBe(5)->and($t->suits)->toBeNull();
});

it('store rejects a trigger that sets both suits and margin_cost', function () {
    $action = Action::factory()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.triggers.store'), [
        'action_id' => $action->id,
        'name' => 'Invalid',
        'suits' => 'R',
        'margin_cost' => 3,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['suits', 'margin_cost']);

    expect(Trigger::where('name', 'Invalid')->exists())->toBeFalse();
});

it('update rejects a trigger that sets both suits and margin_cost', function () {
    $t = Trigger::factory()->for(Action::factory()->create(), 'action')->create(['suits' => 'M', 'margin_cost' => null]);

    $this->actingAs($this->admin)->postJson(route('admin.tos.triggers.update', $t->id), [
        'action_id' => $t->action_id,
        'name' => $t->name,
        'suits' => 'M',
        'margin_cost' => 4,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['suits', 'margin_cost']);

    expect($t->fresh()->suits)->toBe('M')->and($t->fresh()->margin_cost)->toBeNull();
});

it('index denies users without view_tos_trigger', function () {
    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('admin.tos.triggers.index'))->assertForbidden();
});

it('update modifies a Trigger', function () {
    $t = Trigger::factory()->for(Action::factory()->create(), 'action')->create(['name' => 'Original', 'suits' => 'R']);

    $this->actingAs($this->admin)->post(route('admin.tos.triggers.update', $t->id), [
        'action_id' => $t->action_id,
        'name' => 'Renamed',
        'suits' => 'M',
    ])->assertRedirect();

    $t->refresh();
    expect($t->name)->toBe('Renamed')->and($t->suits)->toBe('M');
});

it('delete removes the Trigger', function () {
    $t = Trigger::factory()->for(Action::factory()->create(), 'action')->create();

    $this->actingAs($this->admin)->post(route('admin.tos.triggers.delete', $t->id))->assertRedirect();

    expect(Trigger::find($t->id))->toBeNull();
});

it('store denies users without edit_tos_trigger', function () {
    $action = Action::factory()->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosTrigger->value);

    $this->actingAs($viewer)->post(route('admin.tos.triggers.store'), [
        'action_id' => $action->id,
        'name' => 'Blocked',
        'suits' => 'R',
    ])->assertForbidden();

    expect(Trigger::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_trigger', function () {
    $t = Trigger::factory()->for(Action::factory()->create(), 'action')->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosTrigger->value);

    $this->actingAs($viewer)->post(route('admin.tos.triggers.update', $t->id), [
        'action_id' => $t->action_id,
        'name' => 'Hijacked',
        'suits' => 'R',
    ])->assertForbidden();

    expect($t->fresh()->name)->toBe('Original');
});

it('delete denies users without delete_tos_trigger', function () {
    $t = Trigger::factory()->for(Action::factory()->create(), 'action')->create();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosTrigger->value);

    $this->actingAs($viewer)->post(route('admin.tos.triggers.delete', $t->id))->assertForbidden();

    expect(Trigger::find($t->id))->not->toBeNull();
});
