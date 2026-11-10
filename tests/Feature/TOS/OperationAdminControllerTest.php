<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\Operation;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosOperation, PermissionEnum::EditTosOperation, PermissionEnum::DeleteTosOperation] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_tos_operation', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.operations.index'))->assertForbidden();
});

it('index lists operations for admins', function () {
    Operation::factory()->create(['name' => 'Listed One']);

    $this->actingAs($this->admin)->get(route('admin.tos.operations.index'))->assertOk();
});

it('store creates a Operation', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.operations.store'), ['name' => 'Brand New', 'source' => 'Core Rulebook', 'victory_conditions' => 'Score points.'])
        ->assertRedirect(route('admin.tos.operations.index'));

    expect(Operation::where('name', 'Brand New')->exists())->toBeTrue();
});

it('store rejects a missing name', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.operations.store'), [])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('store rejects a duplicate name', function () {
    Operation::factory()->create(['name' => 'Taken']);

    $this->actingAs($this->admin)->postJson(route('admin.tos.operations.store'), ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('update changes a Operation and allows keeping its own name', function () {
    $operation = Operation::factory()->create(['name' => 'Before']);

    $this->actingAs($this->admin)->post(route('admin.tos.operations.update', $operation), ['name' => 'Before', 'sort_order' => 5])
        ->assertRedirect(route('admin.tos.operations.index'));

    expect($operation->fresh()->sort_order)->toBe(5);
});

it('update denies users without edit_tos_operation', function () {
    $operation = Operation::factory()->create();

    $this->actingAs($this->stranger)->post(route('admin.tos.operations.update', $operation), ['name' => 'Hacked'])->assertForbidden();
});

it('delete removes a Operation', function () {
    $operation = Operation::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.operations.delete', $operation))
        ->assertRedirect(route('admin.tos.operations.index'));

    expect(Operation::find($operation->id))->toBeNull();
});

it('delete denies users without delete_tos_operation', function () {
    $operation = Operation::factory()->create();

    $this->actingAs($this->stranger)->post(route('admin.tos.operations.delete', $operation))->assertForbidden();
    expect(Operation::find($operation->id))->not->toBeNull();
});

it('generates a deterministic slug from the name', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.operations.store'), ['name' => 'Brand New', 'source' => 'Core Rulebook', 'victory_conditions' => 'Score points.']);

    expect(Operation::where('name', 'Brand New')->value('slug'))->toBe('brand-new');
});
