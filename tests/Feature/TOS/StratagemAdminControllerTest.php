<?php

use App\Enums\PermissionEnum;
use App\Enums\TOS\AllegianceTypeEnum;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Stratagem;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach ([PermissionEnum::ViewTosStratagem, PermissionEnum::EditTosStratagem, PermissionEnum::DeleteTosStratagem] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $role->syncPermissions(Permission::all());
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_tos_stratagem', function () {
    $this->actingAs($this->stranger)->get(route('admin.tos.stratagems.index'))->assertForbidden();
});

it('store creates a Stratagem with a specific Allegiance', function () {
    $ke = Allegiance::factory()->earth()->create();

    // Rulebook p. 13: a Stratagem keys to either a specific Allegiance OR a
    // type-pool, never both — so allegiance_type stays null when allegiance_id
    // is set (and vice versa).
    $this->actingAs($this->admin)->post(route('admin.tos.stratagems.store'), [
        'name' => 'Volley Fire',
        'allegiance_id' => $ke->id,
        'tactical_cost' => 1,
        'effect' => 'Fire!',
    ])->assertRedirect(route('admin.tos.stratagems.index'));

    $s = Stratagem::where('name', 'Volley Fire')->first();
    expect($s)->not->toBeNull()
        ->and($s->allegiance_id)->toBe($ke->id)
        ->and($s->allegiance_type)->toBeNull()
        ->and($s->tactical_cost)->toBe(1);
});

it('store rejects setting both allegiance_id and allegiance_type', function () {
    $ke = Allegiance::factory()->earth()->create();

    $this->actingAs($this->admin)->postJson(route('admin.tos.stratagems.store'), [
        'name' => 'Conflicted',
        'allegiance_id' => $ke->id,
        'allegiance_type' => AllegianceTypeEnum::Earth->value,
        'tactical_cost' => 1,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allegiance_id', 'allegiance_type']);

    expect(Stratagem::where('name', 'Conflicted')->exists())->toBeFalse();
});

it('store allows null allegiance_id with allegiance_type set', function () {
    $this->actingAs($this->admin)->post(route('admin.tos.stratagems.store'), [
        'name' => 'Malifaux Tide',
        'allegiance_id' => null,
        'allegiance_type' => AllegianceTypeEnum::Malifaux->value,
        'tactical_cost' => 2,
    ])->assertRedirect();

    $s = Stratagem::where('name', 'Malifaux Tide')->first();
    expect($s->allegiance_id)->toBeNull()
        ->and($s->allegiance_type)->toBe(AllegianceTypeEnum::Malifaux);
});

it('rejects tactical_cost below 1', function () {
    $this->actingAs($this->admin)->postJson(route('admin.tos.stratagems.store'), [
        'name' => 'Invalid',
        'tactical_cost' => 0,
    ])->assertStatus(422);
});

it('update modifies a stratagem', function () {
    $s = Stratagem::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.stratagems.update', $s->slug), [
        'name' => 'Renamed',
        'tactical_cost' => 3,
        'allegiance_type' => AllegianceTypeEnum::Malifaux->value,
    ])->assertRedirect();

    $s->refresh();
    expect($s->name)->toBe('Renamed')
        ->and($s->tactical_cost)->toBe(3);
});

it('delete removes the stratagem', function () {
    $s = Stratagem::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.tos.stratagems.delete', $s->slug))->assertRedirect();

    expect(Stratagem::find($s->id))->toBeNull();
});

it('store denies users without edit_tos_stratagem', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosStratagem->value);

    $this->actingAs($viewer)->post(route('admin.tos.stratagems.store'), [
        'name' => 'Blocked',
        'tactical_cost' => 1,
    ])->assertForbidden();

    expect(Stratagem::where('name', 'Blocked')->exists())->toBeFalse();
});

it('update denies users without edit_tos_stratagem', function () {
    $s = Stratagem::factory()->create(['name' => 'Original']);
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::ViewTosStratagem->value);

    $this->actingAs($viewer)->post(route('admin.tos.stratagems.update', $s->slug), [
        'name' => 'Hijacked',
        'tactical_cost' => 5,
    ])->assertForbidden();

    expect($s->fresh()->name)->toBe('Original');
});
