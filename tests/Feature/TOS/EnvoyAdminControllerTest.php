<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\EnvoyRestrictionEnum;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Envoy;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([
        PermissionEnum::ViewTosEnvoy,
        PermissionEnum::EditTosEnvoy,
        PermissionEnum::DeleteTosEnvoy,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_tos_envoy', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.envoys.index'))->assertForbidden();
});

it('store creates an envoy and attaches abilities', function () {
    $syndicate = Allegiance::factory()->malifaux()->syndicate()->create();
    $ability = Ability::factory()->general()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.envoys.store'), [
        'allegiance_id' => $syndicate->id,
        'name' => 'Demo Envoy',
        'keyword' => 'Envoy',
        'restriction' => EnvoyRestrictionEnum::Malifaux->value,
        'body' => 'body text',
        'ability_ids' => [$ability->id],
    ])->assertRedirect(route('admin.tos.envoys.index'));

    $envoy = Envoy::where('name', 'Demo Envoy')->first();
    expect($envoy)->not->toBeNull()
        ->and($envoy->restriction)->toBe(EnvoyRestrictionEnum::Malifaux)
        ->and($envoy->abilities->pluck('id'))->toContain($ability->id);
});

it('store rejects invalid restriction value', function () {
    $syndicate = Allegiance::factory()->syndicate()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.envoys.store'), [
        'allegiance_id' => $syndicate->id,
        'name' => 'Bad',
        'restriction' => 'not-a-restriction',
    ])->assertStatus(422);
});

it('update modifies an envoy', function () {
    $envoy = Envoy::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.envoys.update', $envoy->slug), [
        'allegiance_id' => $envoy->allegiance_id,
        'name' => 'Renamed Envoy',
        'restriction' => EnvoyRestrictionEnum::Earth->value,
    ])->assertRedirect();

    $envoy->refresh();
    expect($envoy->name)->toBe('Renamed Envoy')
        ->and($envoy->restriction)->toBe(EnvoyRestrictionEnum::Earth);
});

it('delete removes the envoy', function () {
    $envoy = Envoy::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.envoys.delete', $envoy->slug))
        ->assertRedirect(route('admin.tos.envoys.index'));

    expect(Envoy::find($envoy->id))->toBeNull();
});

it('store denies users without edit_tos_envoy', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosEnvoy->value);
    $syndicate = Allegiance::factory()->syndicate()->create();

    $this->actingAs($viewer)->post(route('admin.tos.envoys.store'), [
        'allegiance_id' => $syndicate->id,
        'name' => 'Blocked',
        'restriction' => 'malifaux',
    ])->assertForbidden();

    expect(Envoy::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_envoy', function () {
    $envoy = Envoy::factory()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosEnvoy->value);

    $this->actingAs($viewer)->post(route('admin.tos.envoys.update', $envoy->slug), [
        'allegiance_id' => $envoy->allegiance_id,
        'name' => 'Hijacked',
        'restriction' => EnvoyRestrictionEnum::Malifaux->value,
    ])->assertForbidden();

    expect($envoy->fresh()->name)->toBe('Original');
});
