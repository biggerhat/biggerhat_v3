<?php

use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (PermissionEnum::cases() as $perm) {
        Permission::firstOrCreate(['name' => $perm->value]);
    }
    Role::firstOrCreate(['name' => 'super_admin'])->syncPermissions(Permission::all());
});

function cgLifeUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->givePermissionTo(PermissionEnum::UseCampaignMode->value);

    return $user;
}

/** @return array{User, User, Campaign, CampaignCrew, CampaignCrew, Game, CampaignGame} */
function campaignGameWithBase(): array
{
    $userA = cgLifeUser();
    $userB = cgLifeUser();
    $campaign = Campaign::factory()->active()->create([
        'organizer_user_id' => $userA->id,
        'current_week' => 2,
    ]);
    CampaignPlayer::factory()->organizer()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id]);
    CampaignPlayer::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id]);
    $crewA = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userA->id, 'faction' => FactionEnum::Arcanists->value]);
    $crewB = CampaignCrew::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $userB->id, 'faction' => FactionEnum::Guild->value]);

    $game = Game::factory()->create([
        'format' => GameFormatEnum::Campaign->value,
        'status' => GameStatusEnum::InProgress->value,
        'creator_id' => $userA->id,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userA->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $userB->id, 'slot' => 2]);
    $cg = CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'crew_b_id' => $crewB->id,
        'base_game_id' => $game->id,
    ]);

    return [$userA, $userB, $campaign, $crewA, $crewB, $game, $cg];
}

// ───── Game completion → total_wins increment ─────

it('increments winning crew total_wins when a campaign game completes', function () {
    [$userA, , , $crewA, $crewB, $game, $cg] = campaignGameWithBase();

    $game->update([
        'status' => GameStatusEnum::Completed->value,
        'winner_id' => $userA->id,
        'winner_slot' => 1,
        'is_tie' => false,
        'completed_at' => now(),
    ]);

    expect($crewA->fresh()->total_wins)->toBe(1);
    expect($crewB->fresh()->total_wins)->toBe(0);
    expect($cg->fresh()->winner_crew_id)->toBe($crewA->id);
    expect($cg->fresh()->status)->toBe('closed');
});

it('writes vp_a and vp_b from GamePlayer total_points on game completion', function () {
    [$userA, , , , , $game, $cg] = campaignGameWithBase();

    // Set per-player total_points (where the in-game scoring really lives).
    \App\Models\GamePlayer::where('game_id', $game->id)->where('slot', 1)->update(['total_points' => 7]);
    \App\Models\GamePlayer::where('game_id', $game->id)->where('slot', 2)->update(['total_points' => 4]);

    $game->update([
        'status' => GameStatusEnum::Completed->value,
        'winner_id' => $userA->id,
        'winner_slot' => 1,
        'is_tie' => false,
        'completed_at' => now(),
    ]);

    expect($cg->fresh()->vp_a)->toBe(7);
    expect($cg->fresh()->vp_b)->toBe(4);
});

it('does not increment total_wins on a tie', function () {
    [, , , $crewA, $crewB, $game] = campaignGameWithBase();

    $game->update([
        'status' => GameStatusEnum::Completed->value,
        'winner_id' => null,
        'winner_slot' => null,
        'is_tie' => true,
        'completed_at' => now(),
    ]);

    expect($crewA->fresh()->total_wins)->toBe(0);
    expect($crewB->fresh()->total_wins)->toBe(0);
});

it('skips total_wins logic on non-campaign games', function () {
    $user = cgLifeUser();
    $game = Game::factory()->create([
        'format' => GameFormatEnum::Standard->value,
        'status' => GameStatusEnum::InProgress->value,
        'creator_id' => $user->id,
    ]);

    // Completing a non-campaign game should be a no-op.
    $game->update([
        'status' => GameStatusEnum::Completed->value,
        'winner_id' => $user->id,
        'winner_slot' => 1,
        'is_tie' => false,
    ]);

    // No CampaignGame row exists for this game — observer should bail
    // without throwing.
    expect(CampaignGame::query()->where('base_game_id', $game->id)->exists())->toBeFalse();
});

it('skips total_wins on status changes that are not Completed', function () {
    [$userA, , , $crewA, , $game] = campaignGameWithBase();

    $game->update([
        'status' => GameStatusEnum::Abandoned->value,
        'winner_id' => $userA->id,
    ]);

    expect($crewA->fresh()->total_wins)->toBe(0);
});

// ───── Auto-detect killed models ─────

it('Aftermath show payload pulls killed models from GameCrewMember.is_killed', function () {
    [$userA, , , $crewA, , $game] = campaignGameWithBase();
    $cg = CampaignGame::where('base_game_id', $game->id)->first();

    $deadChar = Character::factory()->create(['display_name' => 'Dead Walker', 'station' => 'minion']);
    $aliveChar = Character::factory()->create(['display_name' => 'Lucky Walker', 'station' => 'minion']);

    $deadArsenal = CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'character_id' => $deadChar->id,
        'is_peon' => false,
    ]);
    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'character_id' => $aliveChar->id,
        'is_peon' => false,
    ]);

    GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => GamePlayer::where('game_id', $game->id)->where('slot', 1)->first()->id,
        'character_id' => $deadChar->id,
        'is_killed' => true,
    ]);

    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $cg->id,
        'campaign_crew_id' => $crewA->id,
    ]);

    $this->actingAs($userA)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('killed_models', 1)
            ->where('killed_models.0.id', $deadArsenal->id)
        );
});

it('Aftermath show returns empty killed_models when no GameCrewMember deaths recorded', function () {
    [$userA, , , $crewA, , $game] = campaignGameWithBase();
    $cg = CampaignGame::where('base_game_id', $game->id)->first();

    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'is_peon' => false,
    ]);

    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $cg->id,
        'campaign_crew_id' => $crewA->id,
    ]);

    $this->actingAs($userA)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('killed_models', 0));
});

it('Aftermath show falls back to all active arsenal models when no base game is linked', function () {
    [$userA, , $campaign, $crewA] = campaignGameWithBase();
    // Manually create a campaign game with NO base_game_id linked.
    $detachedCg = CampaignGame::factory()->create([
        'campaign_id' => $campaign->id,
        'crew_a_id' => $crewA->id,
        'base_game_id' => null,
    ]);

    CampaignArsenalModel::factory()->count(2)->create([
        'campaign_crew_id' => $crewA->id,
        'is_peon' => false,
    ]);
    CampaignArsenalModel::factory()->create([
        'campaign_crew_id' => $crewA->id,
        'is_peon' => true, // peons excluded
    ]);

    $aftermath = CampaignAftermath::factory()->create([
        'campaign_game_id' => $detachedCg->id,
        'campaign_crew_id' => $crewA->id,
    ]);

    $this->actingAs($userA)
        ->get(route('campaigns.aftermaths.show', $aftermath))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('killed_models', 2));
});
