<?php

use App\Enums\GameStatusEnum;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;

/**
 * The Crew Builder's "Use this crew in game" button posts here (with `slot` for solo games), so the
 * crew-select submit endpoint is what a build-from-a-game round trip depends on.
 */
function crewSelectSoloGame(User $creator): Game
{
    $game = Game::factory()->solo()->create(['creator_id' => $creator->id, 'status' => GameStatusEnum::CrewSelect]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $creator->id, 'slot' => 1, 'faction' => 'arcanists']);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2, 'opponent_name' => 'Opponent', 'faction' => 'guild']);

    return $game;
}

function masterBuild(User $owner, string $faction): CrewBuild
{
    $master = Character::factory()->create(['station' => 'master', 'faction' => $faction]);

    return CrewBuild::factory()->create(['user_id' => $owner->id, 'faction' => $faction, 'master_id' => $master->id, 'crew_data' => []]);
}

it('attaches the submitted crew to the slot named in a solo game', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);
    $build = masterBuild($creator, 'guild');

    $this->actingAs($creator)
        ->postJson(route('games.setup.crew', $game->uuid), ['crew_build_id' => $build->id, 'slot' => 2])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($game->players()->where('slot', 2)->first()->crew_build_id)->toBe($build->id)
        ->and($game->players()->where('slot', 1)->first()->crew_build_id)->toBeNull();
});

it('refuses a crew build that belongs to someone else', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);
    $build = masterBuild(User::factory()->create(), 'arcanists');

    $this->actingAs($creator)
        ->postJson(route('games.setup.crew', $game->uuid), ['crew_build_id' => $build->id, 'slot' => 1])
        ->assertForbidden();

    expect($game->players()->where('slot', 1)->first()->crew_build_id)->toBeNull();
});

it('rejects a crew submission once the game has left crew select', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);
    $game->update(['status' => GameStatusEnum::SchemeSelect]);
    $build = masterBuild($creator, 'arcanists');

    $this->actingAs($creator)
        ->postJson(route('games.setup.crew', $game->uuid), ['crew_build_id' => $build->id, 'slot' => 1])
        ->assertUnprocessable()
        ->assertJson(['error' => 'Invalid game state']);
});

it('does not let another user pick a solo game\'s slot', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);
    $intruder = User::factory()->create();
    $build = masterBuild($intruder, 'guild');

    $this->actingAs($intruder)
        ->postJson(route('games.setup.crew', $game->uuid), ['crew_build_id' => $build->id, 'slot' => 2])
        ->assertForbidden();
});
