<?php

use App\Enums\Campaign\LeaderArchetypeEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Campaign\CampaignPlayer;
use App\Models\CustomCharacter;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
});

function lciUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

function lciCrew(User $user): CampaignCrew
{
    $campaign = Campaign::factory()->create(['organizer_user_id' => $user->id]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);

    return CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $user->id]);
}

/** @return array{Campaign, CampaignCrew, CustomCharacter} a leader with earned tier-1 (index 0) and tier-2 (index 2) boxes. */
function lciLeaderWithEarnedBox(User $user): array
{
    $crew = lciCrew($user);
    $track = CustomCharacter::defaultXpTrack();
    $track[0]['filled'] = true;
    $track[2]['filled'] = true;

    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'Card Leader',
        'faction' => FactionEnum::Guild->value,
        'station' => 'master',
        'health' => 12, 'defense' => 5, 'willpower' => 6, 'speed' => 5,
        'xp_track' => $track,
    ]);

    return [$crew->campaign, $crew, $leader];
}

it('queues a card regeneration when a new Leader is saved via the Leader Builder', function () {
    Bus::fake();
    $user = lciUser();
    $crew = lciCrew($user);
    $faction = FactionEnum::Guild;
    $kw1 = \App\Models\Keyword::factory()->create();
    \App\Models\Character::factory()->create(['faction' => $faction])->keywords()->attach($kw1);
    $kw2 = \App\Models\Keyword::factory()->create();

    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.update', [$crew->campaign_id, $crew->share_code]), [
            'name' => 'New Leader',
            'archetype' => LeaderArchetypeEnum::Generalist->value,
            'tag' => LeaderTagEnum::Bruiser->value,
            'faction' => $faction->value,
            'keyword_1_id' => $kw1->id, 'keyword_2_id' => $kw2->id,
            'size' => 2, 'base' => 30,
            'actions' => [],
            'abilities' => [],
        ])
        ->assertRedirect();

    $leader = CustomCharacter::where('campaign_crew_id', $crew->id)->where('current', true)->firstOrFail();
    Bus::assertDispatched(GenerateLeaderCardImage::class, fn ($job) => $job->customCharacterId === $leader->id);
});

it('queues a card regeneration when an advancement is logged directly from the Arsenal Sheet', function () {
    $user = lciUser();
    [$campaign, $crew, $leader] = lciLeaderWithEarnedBox($user);
    // A bespoke (no ability_id/is_joker) Ability advancement unconditionally
    // appends to the target's abilities[] and saves — unlike AttackMod/
    // TacticalMod, it needs no applied_to_action_index to take effect.
    $ability = \App\Models\Campaign\AdvancementAbility::factory()->create(['talent_name' => 'Card Test Ability']);

    Bus::fake();
    $this->actingAs($user)
        ->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
            'position_in_xp_track' => 2,
            'source_table' => 'ability',
            'catalog_id' => $ability->id,
        ])
        ->assertRedirect();

    Bus::assertDispatched(GenerateLeaderCardImage::class, fn ($job) => $job->customCharacterId === $leader->id);
});

it('queues a card regeneration when a logged advancement is removed', function () {
    $user = lciUser();
    [$campaign, $crew, $leader] = lciLeaderWithEarnedBox($user);
    $ability = \App\Models\Campaign\AdvancementAbility::factory()->create(['talent_name' => 'Card Test Ability']);

    $this->actingAs($user)->post(route('campaigns.crews.leader.advancements.store', [$campaign->id, $crew->share_code]), [
        'position_in_xp_track' => 2,
        'source_table' => 'ability',
        'catalog_id' => $ability->id,
    ]);
    $logged = CampaignLeaderAdvancement::where('custom_character_id', $leader->id)->firstOrFail();

    Bus::fake();
    $this->actingAs($user)
        ->delete(route('campaigns.crews.leader.advancements.destroy', [$campaign->id, $crew->share_code, $logged]))
        ->assertRedirect();

    Bus::assertDispatched(GenerateLeaderCardImage::class, fn ($job) => $job->customCharacterId === $leader->id);
});

it('queues a card regeneration for a Totem created via an Advance Leader advancement', function () {
    Bus::fake();
    $user = lciUser();
    $crew = lciCrew($user);
    $leader = CustomCharacter::create([
        'user_id' => $user->id,
        'campaign_crew_id' => $crew->id,
        'is_campaign_leader' => true,
        'current' => true,
        'name' => 'TestLeader',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 14, 'defense' => 5, 'willpower' => 5, 'speed' => 6, 'base' => 30,
    ]);
    $opponent = CampaignCrew::factory()->create(['campaign_id' => $crew->campaign_id]);
    $game = \App\Models\Campaign\CampaignGame::factory()->create([
        'campaign_id' => $crew->campaign_id,
        'crew_a_id' => $crew->id,
        'crew_b_id' => $opponent->id,
    ]);
    $aftermath = \App\Models\Campaign\CampaignAftermath::factory()->create([
        'campaign_game_id' => $game->id,
        'campaign_crew_id' => $crew->id,
        'current_phase' => 4,
        'hand_drawn' => [],
    ]);
    $totemTemplate = CustomCharacter::create([
        'user_id' => $user->id,
        'is_campaign_totem_template' => true,
        'name' => 'Wisp',
        'faction' => FactionEnum::Resurrectionists->value,
        'health' => 3, 'defense' => 4, 'willpower' => 4, 'speed' => 5, 'base' => 30,
    ]);

    $this->actingAs($user)
        ->post(route('campaigns.aftermaths.advance-leader', $aftermath), [
            'bruiser_killed_non_peon' => false,
            'strategist_interacted' => false,
            'lost' => false,
            'advancements' => [[
                'source_table' => 'totem',
                'catalog_id' => $totemTemplate->id,
                'position_in_xp_track' => 4,
            ]],
        ])
        ->assertRedirect();

    $totem = CustomCharacter::where('is_campaign_totem', true)->where('campaign_crew_id', $crew->id)->firstOrFail();
    Bus::assertDispatched(GenerateLeaderCardImage::class, fn ($job) => $job->customCharacterId === $totem->id);
});

it('does not queue a card regeneration for an unrelated field change', function () {
    $user = lciUser();
    [, , $leader] = lciLeaderWithEarnedBox($user);

    Bus::fake();
    $leader->update(['notes' => 'Just a note, not a card change.']);

    Bus::assertNotDispatched(GenerateLeaderCardImage::class);
});

it('does not queue a card regeneration for a non-Campaign homebrew character', function () {
    $user = lciUser();

    Bus::fake();
    CustomCharacter::create([
        'user_id' => $user->id,
        'name' => 'Homebrew Master',
        'faction' => FactionEnum::Guild->value,
        'health' => 10, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'base' => 30,
    ]);

    Bus::assertNotDispatched(GenerateLeaderCardImage::class);
});
