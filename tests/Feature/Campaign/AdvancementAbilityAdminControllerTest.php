<?php

use App\Enums\PermissionEnum;
use App\Models\Ability;
use App\Models\Campaign\AdvancementAbility;
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
        ->get(route('admin.campaign.advancement-ability.index'))
        ->assertForbidden();
});

it('store creates a bespoke Ability row', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-ability.store'), [
            'flip_value' => 10,
            'is_joker' => false,
            'is_always_available' => false,
            'talent_name' => 'Bespoke Ability',
            'effect_text' => 'Does a thing.',
            'ability_id' => null,
            'suits' => 'tome',
        ])
        ->assertRedirect(route('admin.campaign.advancement-ability.index'));

    $row = AdvancementAbility::firstWhere('talent_name', 'Bespoke Ability');
    expect($row)->not->toBeNull();
    expect($row->ability_id)->toBeNull();
});

it('store links an Ability Lookup row', function () {
    $ability = Ability::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-ability.store'), [
            'flip_value' => 13,
            'is_joker' => false,
            'is_always_available' => false,
            'talent_name' => 'Reused Ability',
            'effect_text' => 'Gains an existing ability.',
            'ability_id' => $ability->id,
        ])
        ->assertRedirect();

    $row = AdvancementAbility::firstWhere('talent_name', 'Reused Ability');
    expect($row->ability_id)->toBe($ability->id);
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.advancement-ability.store'), [
            'is_joker' => false, 'is_always_available' => false,
            'talent_name' => 'Nope', 'effect_text' => 'x',
        ])
        ->assertForbidden();
});

it('store rejects a non-existent Ability lookup id', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-ability.store'), [
            'is_joker' => false, 'is_always_available' => false,
            'talent_name' => 'Bad Link', 'effect_text' => 'x',
            'ability_id' => 999999,
        ])
        ->assertSessionHasErrors('ability_id');
});

it('update changes an Ability advancement row', function () {
    $row = AdvancementAbility::factory()->create(['talent_name' => 'Old']);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-ability.update', $row->id), [
            'flip_value' => $row->flip_value,
            'is_joker' => false, 'is_always_available' => false,
            'talent_name' => 'New',
            'effect_text' => $row->effect_text,
        ])
        ->assertRedirect();

    expect($row->fresh()->talent_name)->toBe('New');
});

it('delete removes an Ability advancement row', function () {
    $row = AdvancementAbility::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-ability.delete', $row->id))
        ->assertRedirect();

    expect(AdvancementAbility::find($row->id))->toBeNull();
});
