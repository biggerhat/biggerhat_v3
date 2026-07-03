<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Trigger;
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
        ->get(route('admin.campaign.advancement-attack-mod.index'))
        ->assertForbidden();
});

it('store creates a bespoke Attack Mod row with no Trigger lookup', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-attack-mod.store'), [
            'flip_value' => 7,
            'is_black_joker' => false,
            'is_red_joker' => false,
            'is_always_available' => false,
            'modifier_type' => 'skl_boost',
            'name' => 'Sharpened Aim',
            'effect_text' => 'Bump Skl by 1.',
            'suit' => null,
            'skl_from' => 5,
            'skl_to' => 6,
            'trigger_id' => null,
        ])
        ->assertRedirect(route('admin.campaign.advancement-attack-mod.index'));

    $row = AdvancementAttackMod::firstWhere('name', 'Sharpened Aim');
    expect($row)->not->toBeNull();
    expect($row->modifier_type)->toBe('skl_boost');
    expect($row->trigger_id)->toBeNull();
});

it('store links a Trigger Lookup row', function () {
    $trigger = Trigger::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-tactical-mod.store'), [
            'flip_value' => 3,
            'is_black_joker' => false,
            'is_red_joker' => false,
            'is_always_available' => false,
            'modifier_type' => 'trigger',
            'name' => 'Reused Trigger',
            'effect_text' => 'Gain a named trigger.',
            'suit' => 'ram',
            'trigger_id' => $trigger->id,
        ])
        ->assertRedirect(route('admin.campaign.advancement-tactical-mod.index'));

    $row = AdvancementTacticalMod::firstWhere('name', 'Reused Trigger');
    expect($row->trigger_id)->toBe($trigger->id);
});

it('store denies users without edit_campaign_catalog', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(PermissionEnum::UseCampaignMode->value);
    $viewer->givePermissionTo(PermissionEnum::ViewCampaignCatalog->value);

    $this->actingAs($viewer)
        ->post(route('admin.campaign.advancement-attack-mod.store'), [
            'is_black_joker' => false, 'is_red_joker' => false, 'is_always_available' => false,
            'modifier_type' => 'trigger', 'name' => 'Nope', 'effect_text' => 'x',
        ])
        ->assertForbidden();
});

it('store rejects an invalid modifier_type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-attack-mod.store'), [
            'is_black_joker' => false, 'is_red_joker' => false, 'is_always_available' => false,
            'modifier_type' => 'not-a-real-type', 'name' => 'Bad', 'effect_text' => 'x',
        ])
        ->assertSessionHasErrors('modifier_type');
});

it('update changes an Attack Mod row', function () {
    $row = AdvancementAttackMod::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-attack-mod.update', $row->id), [
            'flip_value' => $row->flip_value,
            'is_black_joker' => false, 'is_red_joker' => false, 'is_always_available' => false,
            'modifier_type' => $row->modifier_type,
            'name' => 'New Name',
            'effect_text' => $row->effect_text,
        ])
        ->assertRedirect();

    expect($row->fresh()->name)->toBe('New Name');
});

it('delete removes an Attack Mod row', function () {
    $row = AdvancementAttackMod::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.campaign.advancement-attack-mod.delete', $row->id))
        ->assertRedirect();

    expect(AdvancementAttackMod::find($row->id))->toBeNull();
});
