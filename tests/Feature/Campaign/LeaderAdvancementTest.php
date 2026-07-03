<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\AdvancementAttackMod;
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
