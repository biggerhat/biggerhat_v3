<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;

beforeEach(function () {
    $this->strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $this->schemes = Scheme::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
});

// ─── Authentication ───

it('requires auth for game index', function () {
    $this->get(route('games.index'))->assertRedirect('/login');
});

it('requires auth for game creation', function () {
    $this->post(route('games.store'), [
        'encounter_size' => 50,
        'season' => 'core',
    ])->assertRedirect('/login');
});

it('requires auth for game show', function () {
    $game = Game::factory()->create();
    $this->get(route('games.show', $game->uuid))->assertRedirect('/login');
});

// ─── Authorization ───

it('prevents non-participants from viewing a game', function () {
    $game = Game::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('games.show', $game->uuid))
        ->assertForbidden();
});

it('allows creator to view their game', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)->get(route('games.show', $game->uuid))
        ->assertOk();
});

// ─── Observer Access ───

it('allows public observation when game is observable', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user->id,
        'is_observable' => true,
    ]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    // No auth needed for observe
    $this->get(route('games.observe', $game->uuid))->assertOk();
});

it('returns 404 for non-observable games', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user->id,
        'is_observable' => false,
    ]);

    $this->get(route('games.observe', $game->uuid))->assertNotFound();
});

// ─── Summary Access ───

it('allows public summary access for completed games', function () {
    $user = User::factory()->create();
    $game = Game::factory()->completed()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => User::factory(), 'slot' => 2]);

    $this->get(route('games.summary', $game->uuid))->assertOk();
});

it('returns 404 for summary of in-progress games', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id]);

    $this->get(route('games.summary', $game->uuid))->assertNotFound();
});

// ─── Solo Mode Access ───

it('prevents joining a solo game', function () {
    $creator = User::factory()->create();
    $game = Game::factory()->solo()->create(['creator_id' => $creator->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $creator->id, 'slot' => 1]);
    GamePlayer::factory()->soloOpponent()->create(['game_id' => $game->id]);

    $joiner = User::factory()->create();
    $this->actingAs($joiner)->get(route('games.join', $game->uuid))
        ->assertRedirect();
});

// ─── Setup Step Guards ───

it('prevents faction submission in wrong status', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1]);

    $this->actingAs($user)->postJson(route('games.setup.faction', $game->uuid), [
        'faction' => 'guild',
    ])->assertStatus(422);
});
