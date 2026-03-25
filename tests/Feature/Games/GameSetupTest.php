<?php

use App\Enums\FactionEnum;
use App\Enums\GameStatusEnum;
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

// ─── Game Creation ───

it('creates a 2-player game', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('games.store'), [
        'encounter_size' => 50,
        'season' => 'core',
    ]);
    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $game = Game::latest('id')->first();
    expect($game)->not->toBeNull();
    expect($game->creator_id)->toBe($user->id);
    expect($game->encounter_size)->toBe(50);
    expect($game->status->value)->toBe('setup');
    expect($game->is_solo)->toBeFalse();
});

it('creates a solo game and skips setup phase', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), [
        'encounter_size' => 50,
        'season' => 'core',
        'is_solo' => true,
    ]);

    $game = Game::latest('id')->first();
    expect($game)->not->toBeNull();
    expect($game->is_solo)->toBeTrue();
    expect($game->status->value)->toBe('faction_select');
    expect($game->players)->toHaveCount(2);
    expect($game->players->where('slot', 2)->first()->user_id)->toBeNull();
});

it('creates two players when a solo game is created', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), [
        'encounter_size' => 50,
        'season' => 'core',
        'is_solo' => true,
    ]);

    $game = Game::latest('id')->first();
    $p1 = $game->players->firstWhere('slot', 1);
    $p2 = $game->players->firstWhere('slot', 2);

    expect($p1->user_id)->toBe($user->id);
    expect($p2->user_id)->toBeNull();
    expect($p2->opponent_name)->toBe('Opponent');
});

// ─── Joining ───

it('allows a second player to join a 2-player game', function () {
    $creator = User::factory()->create();
    $joiner = User::factory()->create();

    $this->actingAs($creator)->post(route('games.store'), [
        'encounter_size' => 50,
        'season' => 'core',
    ]);

    $game = Game::latest('id')->first();

    $this->actingAs($joiner)->get(route('games.join', $game->uuid));

    $game->refresh()->load('players');
    expect($game->players)->toHaveCount(2);
    expect($game->status->value)->toBe('faction_select');
    expect($game->players->firstWhere('slot', 2)->user_id)->toBe($joiner->id);
});

it('rejects joining a solo game', function () {
    $creator = User::factory()->create();
    $joiner = User::factory()->create();

    $game = Game::factory()->solo()->create(['creator_id' => $creator->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $creator->id, 'slot' => 1]);
    GamePlayer::factory()->soloOpponent()->create(['game_id' => $game->id]);

    $this->actingAs($joiner)->get(route('games.join', $game->uuid))
        ->assertRedirect();
});

// ─── Faction Selection ───

it('transitions to master select when both factions are submitted', function () {
    $game = Game::factory()->create(['status' => GameStatusEnum::FactionSelect]);
    $p1 = GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 1, 'faction' => null]);
    $p2 = GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 2, 'user_id' => User::factory(), 'faction' => null]);

    $this->actingAs($p1->user)->postJson(route('games.setup.faction', $game->uuid), [
        'faction' => FactionEnum::Guild->value,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('faction_select'); // Only one done

    $this->actingAs($p2->user)->postJson(route('games.setup.faction', $game->uuid), [
        'faction' => FactionEnum::Arcanists->value,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('master_select');
});

it('handles solo faction selection sequentially', function () {
    $user = User::factory()->create();
    $game = Game::factory()->solo()->create(['creator_id' => $user->id]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 1, 'user_id' => $user->id, 'faction' => null]);
    GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 2, 'user_id' => null, 'faction' => null]);

    // Player 1 faction
    $this->actingAs($user)->postJson(route('games.setup.faction', $game->uuid), [
        'faction' => FactionEnum::Guild->value,
        'slot' => 1,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('faction_select'); // Still waiting for slot 2

    // Opponent faction
    $this->actingAs($user)->postJson(route('games.setup.faction', $game->uuid), [
        'faction' => FactionEnum::Bayou->value,
        'slot' => 2,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('master_select');
});

// ─── Scheme Selection ───

it('transitions to in_progress when both schemes are selected', function () {
    $game = Game::factory()->create([
        'status' => GameStatusEnum::SchemeSelect,
        'scheme_pool' => $this->schemes->pluck('id')->toArray(),
    ]);
    $p1 = GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 1]);
    $p2 = GamePlayer::factory()->create(['game_id' => $game->id, 'slot' => 2, 'user_id' => User::factory()]);

    $this->actingAs($p1->user)->postJson(route('games.setup.scheme', $game->uuid), [
        'scheme_id' => $this->schemes[0]->id,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('scheme_select');

    $this->actingAs($p2->user)->postJson(route('games.setup.scheme', $game->uuid), [
        'scheme_id' => $this->schemes[1]->id,
    ])->assertOk();

    $game->refresh();
    expect($game->status->value)->toBe('in_progress');
    expect($game->current_turn)->toBe(1);
});
