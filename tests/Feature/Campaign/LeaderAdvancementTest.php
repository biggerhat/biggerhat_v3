<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CustomCharacter;
use App\Models\User;
use App\Services\Campaign\LeaderAdvancementService;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

function advUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

/** @return array{Campaign, CampaignCrew, CustomCharacter} */
function leaderWithEarnedTier1Box(User $user): array
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
    $crew = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    // Box index 0 is a tier-1 box in the canonical track — mark it earned.
    $track = CustomCharacter::defaultXpTrack();
    $track[0]['filled'] = true;

    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'share_code' => 'ldradvtest1',
        'name' => 'Adv Leader',
        'display_name' => 'Adv Leader',
        'slug' => 'adv-leader',
        'faction' => 'guild',
        'station' => 'master',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5,
        'tag' => 'bruiser',
        'xp_track' => $track,
    ]);

    return [$campaign, $crew, $leader];
}

it('logs a leader advancement at an earned tier box', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $advancement = AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $advancement->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->where('position_in_xp_track', 0)->count())->toBe(1);
});

it('rejects an advancement at an unearned box', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);

    // Box index 2 is a tier-2 box but is NOT filled in our track.
    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 2,
            'source_table' => 'action',
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('rejects a too-high-tier advancement at a low-tier box', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user); // box 0 = tier 1

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'totem', // tier 3 at a tier-1 box
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('rejects a second advancement on a box that already has one', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => 'attack_mod',
        'position_in_xp_track' => 0,
        'applied_to_action_index' => -1,
        'acquired_at' => now(),
    ]);
    $advancement = AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $advancement->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(1);
});

it('removes a logged advancement', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $adv = CampaignLeaderAdvancement::create([
        'custom_character_id' => $leader->id,
        'source_table' => 'attack_mod',
        'position_in_xp_track' => 0,
        'applied_to_action_index' => -1,
        'acquired_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $adv->id]))
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::find($adv->id))->toBeNull();
});

it('applies and reverses a Skl Boost attack-mod advancement on the target action', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => [
        ['name' => 'Test Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]]);
    $sklBoost = AdvancementAttackMod::factory()->sklBoost(5, 6)->create(['flip_value' => 7]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $sklBoost->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['stat'])->toBe(6);

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $advancement->id]))
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['stat'])->toBe(5);
});

it('rejects a Skl Boost pick when the target action\'s current Skl is outside the qualifying range', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => [
        ['name' => 'Test Tactical', 'type' => 'tactical', 'category' => 'tactical', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]]);
    // Qualifying range is Skl 0-1; the action's actual Skl (5) doesn't qualify.
    $sklBoost = \App\Models\Campaign\AdvancementTacticalMod::factory()->sklBoost(0, 2, 1)->create(['flip_value' => 7]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'tactical_mod',
            'catalog_id' => $sklBoost->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['stat'])->toBe(5);
    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('applies and reverses a ranged Skl Boost to the action\'s actual prior Skl, not the range minimum', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => [
        ['name' => 'Test Tactical', 'type' => 'tactical', 'category' => 'tactical', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 1, 'triggers' => []],
    ]]);
    // Qualifying range is Skl 0-1; the action's actual Skl (1) is the range's max, not its min.
    $sklBoost = \App\Models\Campaign\AdvancementTacticalMod::factory()->sklBoost(0, 2, 1)->create(['flip_value' => 7]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'tactical_mod',
            'catalog_id' => $sklBoost->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['stat'])->toBe(2);

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->applied_skl_from)->toBe(1);

    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $advancement->id]))
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['stat'])->toBe(1);
});

it('applies and reverses a Signature attack-mod advancement on the target action', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => [
        ['name' => 'Test Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]]);
    $signature = AdvancementAttackMod::factory()->signature()->create(['flip_value' => 13]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $signature->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['is_signature'])->toBeTrue();

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $advancement->id]))
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions[0]['is_signature'])->toBeFalse();
});

it('applies a bespoke Action advancement with no Lookup directly from its own stat block', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $track = $leader->xp_track;
    $track[2]['filled'] = true; // box index 2 is tier 2 in the canonical track
    $leader->update(['xp_track' => $track]);

    $bespoke = \App\Models\Campaign\AdvancementAction::factory()->create([
        'talent_name' => 'Bespoke Talent',
        'flip_value' => 6,
    ]);

    app(LeaderAdvancementService::class)->create($leader, [[
        'source_table' => 'action',
        'catalog_id' => $bespoke->id,
        'position_in_xp_track' => 2,
    ]], null);

    $leader->refresh();
    expect(collect($leader->actions)->pluck('name'))->toContain('Bespoke Talent');

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->catalog_core_id)->toBe($bespoke->id);
});

it('rejects an Attack Mod Any Joker row without declaring which Joker was flipped', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $joker = AdvancementAttackMod::factory()->anyJoker()->create(['name' => 'Cruel Lessons']);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $joker->id,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('accepts an Attack Mod Any Joker row with either declared color', function (string $color) {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $joker = AdvancementAttackMod::factory()->anyJoker()->create(['name' => 'Cruel Lessons']);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $joker->id,
            'joker_color' => $color,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(1);
})->with(['red', 'black']);

it('rejects a Tactical Mod color-specific Joker row with the wrong declared color', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $redRow = AdvancementTacticalMod::factory()->redJoker()->create(['name' => 'Illumination of Illios']);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'tactical_mod',
            'catalog_id' => $redRow->id,
            'joker_color' => 'black',
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('accepts a Tactical Mod color-specific Joker row with the matching declared color', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $blackRow = AdvancementTacticalMod::factory()->blackJoker()->create(['name' => 'Darkness of Delios']);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'tactical_mod',
            'catalog_id' => $blackRow->id,
            'joker_color' => 'black',
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(1);
});

/** @return CustomCharacter */
function totemForCrew(CampaignCrew $crew, User $user, array $actions = [])
{
    return CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_totem' => true,
        'current' => true,
        'name' => 'Adv Totem',
        'faction' => 'guild',
        'health' => 4, 'defense' => 4, 'willpower' => 4, 'speed' => 5,
        'base' => 30,
        'actions' => $actions,
    ]);
}

it('applies an Attack Mod trigger to the crew\'s Totem instead of the Leader', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => [
        ['name' => 'Leader Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]]);
    $totem = totemForCrew($crew, $user, [
        ['name' => 'Totem Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]);
    $trigger = AdvancementAttackMod::factory()->create(['name' => 'Totem Trigger', 'flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
            'applied_to_custom_character_id' => $totem->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    $totem->refresh();
    expect(collect($totem->actions[0]['triggers'])->pluck('name'))->toContain('Totem Trigger');
    expect($leader->actions[0]['triggers'])->toBeEmpty();

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->applied_to_custom_character_id)->toBe($totem->id);

    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $advancement->id]))
        ->assertRedirect();

    $totem->refresh();
    expect($totem->actions[0]['triggers'])->toBeEmpty();
});

it('rejects targeting a Totem that does not belong to this crew', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $otherUser = advUser();
    $otherCampaign = Campaign::factory()->create(['organizer_user_id' => $otherUser->id]);
    $otherCrew = CampaignCrew::factory()->create(['campaign_id' => $otherCampaign->id, 'user_id' => $otherUser->id]);
    $otherTotem = totemForCrew($otherCrew, $otherUser, [
        ['name' => 'Other Totem Attack', 'type' => 'attack', 'category' => 'attack', 'is_signature' => false, 'stone_cost' => 0, 'stat' => 5, 'triggers' => []],
    ]);
    $trigger = AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
            'applied_to_custom_character_id' => $otherTotem->id,
            'applied_to_action_index' => 0,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('applies an Attack Mod trigger to an Equipment-granted action without mutating the Leader', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $leader->update(['actions' => []]);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $action = \App\Models\Action::factory()->create(['type' => 'attack', 'stat' => 5]);
    $equipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
    $trigger = AdvancementAttackMod::factory()->create(['name' => 'Equipment Trigger', 'flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
            'from_equipment_id' => $equipment->id,
            'applied_to_action_id' => $action->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    $leader->refresh();
    expect($leader->actions)->toBeEmpty();

    $advancement = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();
    expect($advancement->from_equipment_id)->toBe($equipment->id);
    expect($advancement->applied_to_action_id)->toBe($action->id);

    // Nothing was mutated, so removal is a clean no-op mechanically.
    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $advancement->id]))
        ->assertRedirect();
    expect(CampaignLeaderAdvancement::find($advancement->id))->toBeNull();
});

it('rejects targeting Equipment that does not belong to this crew', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $otherEquipment = \App\Models\Campaign\CampaignEquipment::factory()->create();
    $action = \App\Models\Action::factory()->create(['type' => 'attack', 'stat' => 5]);
    $otherEquipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
    $trigger = AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
            'from_equipment_id' => $otherEquipment->id,
            'applied_to_action_id' => $action->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('rejects an action that is not granted by the selected Equipment', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $unrelatedAction = \App\Models\Action::factory()->create(['type' => 'attack', 'stat' => 5]);
    $trigger = AdvancementAttackMod::factory()->create(['flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
            'from_equipment_id' => $equipment->id,
            'applied_to_action_id' => $unrelatedAction->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('rejects an Equipment-targeted Skl Boost when the granted action\'s Skl is outside the qualifying range', function () {
    $user = advUser();
    [$campaign, $crew, $leader] = leaderWithEarnedTier1Box($user);
    $equipment = \App\Models\Campaign\CampaignEquipment::factory()->create(['campaign_crew_id' => $crew->id]);
    $action = \App\Models\Action::factory()->create(['type' => 'attack', 'stat' => 5]);
    $equipment->catalog->actions()->attach($action->id, ['is_signature_action' => false]);
    $sklBoost = AdvancementAttackMod::factory()->sklBoost(2, 6)->create(['flip_value' => 7]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $sklBoost->id,
            'from_equipment_id' => $equipment->id,
            'applied_to_action_id' => $action->id,
            'flip_value' => 13,
        ])
        ->assertRedirect();

    expect(CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->count())->toBe(0);
});

it('blocks a non-owner from logging an advancement', function () {
    $owner = advUser();
    [$campaign, $crew] = leaderWithEarnedTier1Box($owner);
    $other = advUser();

    $this->actingAs($other)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
        ])
        ->assertForbidden();
});
