<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;

beforeEach(function () {
    $this->strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $this->schemes = Scheme::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    $this->user1 = User::factory()->create();
    $this->user2 = User::factory()->create();

    $this->game = Game::factory()->inProgress()->create([
        'creator_id' => $this->user1->id,
        'strategy_id' => $this->strategy->id,
        'scheme_pool' => $this->schemes->pluck('id')->toArray(),
    ]);

    $this->player1 = GamePlayer::factory()->create([
        'game_id' => $this->game->id,
        'user_id' => $this->user1->id,
        'slot' => 1,
        'current_scheme_id' => $this->schemes[0]->id,
        'scheme_pool' => $this->schemes->pluck('id')->toArray(),
    ]);

    $this->player2 = GamePlayer::factory()->create([
        'game_id' => $this->game->id,
        'user_id' => $this->user2->id,
        'slot' => 2,
        'current_scheme_id' => $this->schemes[1]->id,
        'scheme_pool' => $this->schemes->pluck('id')->toArray(),
    ]);
});

// ─── Turn Scoring ───

it('records turn scores correctly', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 2,
        'scheme_action' => 'scored',
    ])->assertOk();

    $turn = GameTurn::where('game_id', $this->game->id)
        ->where('game_player_id', $this->player1->id)
        ->first();

    expect($turn)->not->toBeNull();
    expect($turn->strategy_points)->toBe(1);
    expect($turn->scheme_points)->toBe(2);
    expect($turn->points_scored)->toBe(3);

    $this->player1->refresh();
    expect($this->player1->total_points)->toBe(3);
    expect($this->player1->is_turn_complete)->toBeTrue();
});

it('advances turn when both players submit scores', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 0,
        'scheme_action' => 'held',
    ])->assertOk();

    $this->game->refresh();
    expect($this->game->current_turn)->toBe(1); // Still turn 1

    $this->actingAs($this->user2)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 0,
        'scheme_points' => 1,
        'scheme_action' => 'scored',
    ])->assertOk();

    $this->game->refresh();
    expect($this->game->current_turn)->toBe(2); // Advanced to turn 2

    // Both players reset
    $this->player1->refresh();
    $this->player2->refresh();
    expect($this->player1->is_turn_complete)->toBeFalse();
    expect($this->player2->is_turn_complete)->toBeFalse();
});

it('blocks strategy bonus if already used this game', function () {
    // First turn: use bonus (2 strategy points)
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 2,
        'scheme_points' => 0,
        'scheme_action' => 'held',
    ])->assertOk();

    // Advance turn
    $this->actingAs($this->user2)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 0,
        'scheme_points' => 0,
        'scheme_action' => 'held',
    ])->assertOk();

    // Second turn: try bonus again
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 2,
        'scheme_points' => 0,
        'scheme_action' => 'held',
    ])->assertStatus(422);
});

it('updates scheme for next turn', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 0,
        'scheme_points' => 0,
        'scheme_action' => 'discarded',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $this->player1->refresh();
    expect($this->player1->current_scheme_id)->toBe($this->schemes[2]->id);
});

it('records current scheme in turn even when not scored', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.turns.store', $this->game->uuid), [
        'strategy_points' => 1,
        'scheme_points' => 0,
        'scheme_action' => 'discarded',
        'next_scheme_id' => $this->schemes[2]->id,
    ])->assertOk();

    $turn = GameTurn::where('game_id', $this->game->id)
        ->where('game_player_id', $this->player1->id)
        ->first();

    // Turn should record the original scheme, not the next one
    expect($turn->scheme_id)->toBe($this->schemes[0]->id);
});

// ─── Crew Member Actions ───

it('kills a crew member', function () {
    $member = GameCrewMember::factory()->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player1->id,
    ]);

    $this->actingAs($this->user1)->postJson(route('games.play.crew.kill', [
        'game' => $this->game->uuid,
        'gameCrewMember' => $member->id,
    ]))->assertOk();

    $member->refresh();
    expect($member->is_killed)->toBeTrue();
    expect($member->current_health)->toBe(0);
});

it('revives a crew member at full health', function () {
    $member = GameCrewMember::factory()->killed()->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player1->id,
        'max_health' => 8,
    ]);

    $this->actingAs($this->user1)->postJson(route('games.play.crew.revive', [
        'game' => $this->game->uuid,
        'gameCrewMember' => $member->id,
    ]))->assertOk();

    $member->refresh();
    expect($member->is_killed)->toBeFalse();
    expect($member->current_health)->toBe(8);
});

it('updates crew member health', function () {
    $member = GameCrewMember::factory()->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player1->id,
        'current_health' => 6,
        'max_health' => 6,
    ]);

    $this->actingAs($this->user1)->patchJson(route('games.play.crew.update', [
        'game' => $this->game->uuid,
        'gameCrewMember' => $member->id,
    ]), ['current_health' => 3])->assertOk();

    $member->refresh();
    expect($member->current_health)->toBe(3);
});

it('toggles crew member activation', function () {
    $member = GameCrewMember::factory()->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player1->id,
        'is_activated' => false,
    ]);

    $this->actingAs($this->user1)->patchJson(route('games.play.crew.update', [
        'game' => $this->game->uuid,
        'gameCrewMember' => $member->id,
    ]), ['is_activated' => true])->assertOk();

    $member->refresh();
    expect($member->is_activated)->toBeTrue();
});

// ─── Game Completion ───

it('completes a game when both players agree', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.complete', $this->game->uuid))->assertOk();

    $this->game->refresh();
    expect($this->game->status->value)->toBe('in_progress'); // Only one agreed

    $this->actingAs($this->user2)->postJson(route('games.play.complete', $this->game->uuid))->assertOk();

    $this->game->refresh();
    expect($this->game->status->value)->toBe('completed');
    expect($this->game->completed_at)->not->toBeNull();
});

it('determines the correct winner', function () {
    $this->player1->update(['total_points' => 7]);
    $this->player2->update(['total_points' => 3]);

    $this->actingAs($this->user1)->postJson(route('games.play.complete', $this->game->uuid));
    $this->actingAs($this->user2)->postJson(route('games.play.complete', $this->game->uuid));

    $this->game->refresh();
    expect($this->game->winner_id)->toBe($this->user1->id);
    expect($this->game->winner_slot)->toBe(1);
    expect($this->game->is_tie)->toBeFalse();
});

it('detects a tie', function () {
    $this->player1->update(['total_points' => 5]);
    $this->player2->update(['total_points' => 5]);

    $this->actingAs($this->user1)->postJson(route('games.play.complete', $this->game->uuid));
    $this->actingAs($this->user2)->postJson(route('games.play.complete', $this->game->uuid));

    $this->game->refresh();
    expect($this->game->is_tie)->toBeTrue();
    expect($this->game->winner_id)->toBeNull();
});

it('creates crew snapshots when finalizing without submitted turns', function () {
    GameCrewMember::factory()->count(3)->create([
        'game_id' => $this->game->id,
        'game_player_id' => $this->player1->id,
    ]);

    $this->actingAs($this->user1)->postJson(route('games.play.complete', $this->game->uuid));
    $this->actingAs($this->user2)->postJson(route('games.play.complete', $this->game->uuid));

    // Should have created turn records with snapshots
    $turns = GameTurn::where('game_id', $this->game->id)->get();
    expect($turns)->toHaveCount(2); // One per player

    $p1Turn = $turns->firstWhere('game_player_id', $this->player1->id);
    expect($p1Turn->crew_snapshot)->not->toBeNull();
    expect($p1Turn->crew_snapshot)->toHaveCount(3);
});

it('allows canceling game completion before both agree', function () {
    $this->actingAs($this->user1)->postJson(route('games.play.complete', $this->game->uuid));

    $this->player1->refresh();
    expect($this->player1->is_game_complete)->toBeTrue();

    $this->actingAs($this->user1)->post(route('games.play.cancel_complete', $this->game->uuid))->assertRedirect();

    $this->player1->refresh();
    expect($this->player1->is_game_complete)->toBeFalse();
});

// ─── Abandonment ───

it('abandons a game', function () {
    $this->actingAs($this->user1)->post(route('games.abandon', $this->game->uuid));

    $this->game->refresh();
    expect($this->game->status->value)->toBe('abandoned');
    expect($this->game->completed_at)->not->toBeNull();
});

// ─── Soulstone Pool ───

it('updates soulstone pool', function () {
    $this->actingAs($this->user1)->patchJson(route('games.play.soulstones', $this->game->uuid), [
        'soulstone_pool' => 4,
    ])->assertOk();

    $this->player1->refresh();
    expect($this->player1->soulstone_pool)->toBe(4);
});
