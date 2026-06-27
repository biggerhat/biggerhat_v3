<?php

use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CustomCharacter;
use App\Models\Trigger;
use App\Models\User;
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
    $trigger = Trigger::factory()->create(['campaign_flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
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
    $trigger = Trigger::factory()->create(['campaign_flip_value' => 5]);

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 0,
            'source_table' => 'attack_mod',
            'catalog_id' => $trigger->id,
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
