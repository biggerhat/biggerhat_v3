<?php

use App\Enums\PermissionEnum;
use App\Models\Ability;
use App\Models\Campaign\LuckyMiss;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->stranger = User::factory()->create();
});

it('index denies users without view_campaign_catalog', function () {
    $this->stranger->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    $this->actingAs($this->stranger)
        ->get(route('admin.campaign.lucky-miss.index'))
        ->assertForbidden();
});

it('store creates a flavor-text-only row with no ability lookup', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.lucky-miss.store'), [
            'name' => 'Flavor Only',
            'body' => 'Just some text.',
            'flip_value' => 5,
            'is_doppelganger' => false,
            'ability_id' => null,
        ])
        ->assertRedirect(route('admin.campaign.lucky-miss.index'));

    $row = LuckyMiss::firstWhere('name', 'Flavor Only');
    expect($row)->not->toBeNull();
    expect($row->ability_id)->toBeNull();
});

it('store links an Ability Lookup row', function () {
    $ability = Ability::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.lucky-miss.store'), [
            'name' => 'Grants Ability',
            'body' => 'Gains an existing ability.',
            'flip_value' => 9,
            'is_doppelganger' => false,
            'ability_id' => $ability->id,
        ])
        ->assertRedirect();

    $row = LuckyMiss::firstWhere('name', 'Grants Ability');
    expect($row->ability_id)->toBe($ability->id);
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.lucky-miss.store'), [
            'name' => 'Nope', 'body' => 'x', 'is_doppelganger' => false,
        ])
        ->assertForbidden();
});

it('store rejects a non-existent Ability lookup id', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.lucky-miss.store'), [
            'name' => 'Bad Link', 'body' => 'x', 'is_doppelganger' => false,
            'ability_id' => 999999,
        ])
        ->assertSessionHasErrors('ability_id');
});

it('update changes the linked Ability', function () {
    $row = LuckyMiss::factory()->create(['name' => 'Old']);
    $ability = Ability::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.lucky-miss.update', $row->id), [
            'name' => 'New',
            'body' => $row->body,
            'flip_value' => $row->flip_value,
            'is_doppelganger' => $row->is_doppelganger,
            'ability_id' => $ability->id,
        ])
        ->assertRedirect();

    $fresh = $row->fresh();
    expect($fresh->name)->toBe('New')
        ->and($fresh->ability_id)->toBe($ability->id);
});

it('delete removes a Lucky Miss row', function () {
    $row = LuckyMiss::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.lucky-miss.delete', $row->id))
        ->assertRedirect();

    expect(LuckyMiss::find($row->id))->toBeNull();
});
