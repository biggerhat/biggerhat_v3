<?php

use App\Enums\PermissionEnum;
use App\Models\TOS\Deployment;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosDeployment, PermissionEnum::EditTosDeployment, PermissionEnum::DeleteTosDeployment] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_tos_deployment', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.deployments.index'))->assertForbidden();
});

it('index lists deployments for admins', function () {
    Deployment::factory()->create(['name' => 'Listed One']);

    $this->actingAs($this->admin)->get(route('admin.tos.deployments.index'))->assertOk();
});

it('store creates a Deployment', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.deployments.store'), ['name' => 'Brand New', 'suit' => 'C'])
        ->assertRedirect(route('admin.tos.deployments.index'));

    expect(Deployment::where('name', 'Brand New')->exists())->toBeTrue();
});

it('store rejects a missing name', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.deployments.store'), [])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('store rejects a duplicate name', function () {
    Deployment::factory()->create(['name' => 'Taken']);

    $this->actingAs($this->admin)->postJson(route('admin.tos.deployments.store'), ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('store rejects an unknown suit and a suit already used by another Deployment', function () {
    Deployment::factory()->forSuit('R')->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.deployments.store'), ['name' => 'Bad Suit', 'suit' => 'X'])
        ->assertStatus(422)->assertJsonValidationErrors(['suit']);
    $this->actingAs($this->admin)->postJson(route('admin.tos.deployments.store'), ['name' => 'Dupe Suit', 'suit' => 'R'])
        ->assertStatus(422)->assertJsonValidationErrors(['suit']);
});

it('update changes a Deployment and allows keeping its own name', function () {
    $deployment = Deployment::factory()->create(['name' => 'Before']);

    $this->actingAs($this->admin)->post(route('admin.tos.deployments.update', $deployment), ['name' => 'Before', 'sort_order' => 5])
        ->assertRedirect(route('admin.tos.deployments.index'));

    expect($deployment->fresh()->sort_order)->toBe(5);
});

it('update denies users without edit_tos_deployment', function () {
    $deployment = Deployment::factory()->create();

    $this->actingAs($this->stranger)->post(route('admin.tos.deployments.update', $deployment), ['name' => 'Hacked'])->assertForbidden();
});

it('delete removes a Deployment', function () {
    $deployment = Deployment::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.deployments.delete', $deployment))
        ->assertRedirect(route('admin.tos.deployments.index'));

    expect(Deployment::find($deployment->id))->toBeNull();
});

it('delete denies users without delete_tos_deployment', function () {
    $deployment = Deployment::factory()->create();

    $this->actingAs($this->stranger)->post(route('admin.tos.deployments.delete', $deployment))->assertForbidden();
    expect(Deployment::find($deployment->id))->not->toBeNull();
});

it('generates a deterministic slug from the name', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.deployments.store'), ['name' => 'Brand New', 'suit' => 'C']);

    expect(Deployment::where('name', 'Brand New')->value('slug'))->toBe('brand-new');
});
