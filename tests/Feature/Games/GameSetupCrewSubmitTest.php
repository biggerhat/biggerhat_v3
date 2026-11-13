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

// --- "Track points only": skipping a crew -------------------------------------------------------------------------

function crewSelectDuel(User $one, User $two): Game
{
    $game = Game::factory()->create(['creator_id' => $one->id, 'status' => GameStatusEnum::CrewSelect, 'encounter_size' => 50]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $one->id, 'slot' => 1, 'faction' => 'arcanists']);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $two->id, 'slot' => 2, 'faction' => 'guild']);

    return $game;
}

it('lets a player in a two-player game skip their own crew, and moves on once both are done', function () {
    [$one, $two] = User::factory()->count(2)->create();
    $game = crewSelectDuel($one, $two);

    $this->actingAs($one)->postJson(route('games.setup.crew.skip', $game->uuid))
        ->assertOk()->assertJson(['success' => true, 'both_done' => false]);

    $mine = $game->players()->where('user_id', $one->id)->first();
    expect($mine->crew_skipped)->toBeTrue()
        ->and($mine->soulstone_pool)->toBe(6)
        ->and($game->fresh()->status)->toBe(GameStatusEnum::CrewSelect)
        ->and($game->players()->where('user_id', $two->id)->first()->crew_skipped)->toBeFalse();

    $this->actingAs($two)->postJson(route('games.setup.crew.skip', $game->uuid))
        ->assertOk()->assertJson(['both_done' => true]);

    expect($game->fresh()->status)->toBe(GameStatusEnum::SchemeSelect);
});

it('skips the named side of a solo game', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);

    $this->actingAs($creator)->postJson(route('games.setup.crew.skip', $game->uuid), ['slot' => 1])->assertOk();

    expect($game->players()->where('slot', 1)->first()->crew_skipped)->toBeTrue()
        ->and($game->players()->where('slot', 2)->first()->crew_skipped)->toBeFalse();
});

it('still skips the solo opponent when no slot is given', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);

    $this->actingAs($creator)->postJson(route('games.setup.crew.skip', $game->uuid))->assertOk();

    expect($game->players()->where('slot', 2)->first()->crew_skipped)->toBeTrue()
        ->and($game->players()->where('slot', 1)->first()->crew_skipped)->toBeFalse();
});

it('refuses to skip once a crew is chosen or the game is past crew select', function () {
    [$one, $two] = User::factory()->count(2)->create();
    $game = crewSelectDuel($one, $two);
    $build = masterBuild($one, 'arcanists');
    $this->actingAs($one)->postJson(route('games.setup.crew', $game->uuid), ['crew_build_id' => $build->id])->assertOk();

    $this->actingAs($one)->postJson(route('games.setup.crew.skip', $game->uuid))
        ->assertStatus(422)->assertJson(['error' => 'Invalid game state']);

    $game->update(['status' => GameStatusEnum::InProgress]);
    $this->actingAs($two)->postJson(route('games.setup.crew.skip', $game->uuid))->assertStatus(422);
});

it('does not let someone outside the game skip a crew', function () {
    [$one, $two, $stranger] = User::factory()->count(3)->create();
    $game = crewSelectDuel($one, $two);

    $this->actingAs($stranger)->postJson(route('games.setup.crew.skip', $game->uuid))->assertForbidden();

    expect($game->players()->where('crew_skipped', true)->count())->toBe(0);
});

it('does not let a non-owner pick a solo slot to skip', function () {
    $creator = User::factory()->create();
    $game = crewSelectSoloGame($creator);

    $this->actingAs(User::factory()->create())->postJson(route('games.setup.crew.skip', $game->uuid), ['slot' => 2])->assertForbidden();
});
