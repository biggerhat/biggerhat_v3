<?php

use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;

// ─── Helpers ───

function createSchemeChain(): array
{
    $season = PoolSeasonEnum::GainingGrounds0;
    // Create 9 schemes: 3 pool + 3 follow-ups for each pool scheme
    $pool = Scheme::factory()->count(3)->create(['season' => $season]);

    foreach ($pool as $scheme) {
        $followUps = Scheme::factory()->count(3)->create(['season' => $season]);
        $scheme->update([
            'next_scheme_one_id' => $followUps[0]->id,
            'next_scheme_two_id' => $followUps[1]->id,
            'next_scheme_three_id' => $followUps[2]->id,
        ]);
    }

    return $pool->fresh()->all();
}

function createDuelGame(array $pool, User $user1, User $user2): array
{
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user1->id,
        'strategy_id' => $strategy->id,
        'scheme_pool' => collect($pool)->pluck('id')->toArray(),
    ]);

    $p1 = GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user1->id,
        'slot' => 1,
        'current_scheme_id' => $pool[0]->id,
    ]);

    $p2 = GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user2->id,
        'slot' => 2,
        'current_scheme_id' => $pool[1]->id,
    ]);

    return ['game' => $game, 'p1' => $p1, 'p2' => $p2];
}

function createSoloGame(array $pool, User $user): array
{
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user->id,
        'strategy_id' => $strategy->id,
        'scheme_pool' => collect($pool)->pluck('id')->toArray(),
        'is_solo' => true,
    ]);

    $p1 = GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => 1,
        'current_scheme_id' => $pool[0]->id,
    ]);

    $p2 = GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => null,
        'slot' => 2,
        'current_scheme_id' => null, // Opponent scheme unknown
    ]);

    return ['game' => $game, 'p1' => $p1, 'p2' => $p2];
}

function submitTurn($test, User $user, Game $game, array $data = []): \Illuminate\Testing\TestResponse
{
    return $test->actingAs($user)->postJson(
        route('games.play.turns.store', $game->uuid),
        array_merge(['strategy_points' => 0, 'scheme_points' => 0], $data)
    );
}

// ─── Scheme Selection (Setup Phase) ───

it('allows selecting a scheme from the pool during scheme select', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    $game = Game::factory()->create([
        'creator_id' => $user->id,
        'status' => GameStatusEnum::SchemeSelect,
        'strategy_id' => $strategy->id,
        'scheme_pool' => collect($pool)->pluck('id')->toArray(),
        'current_turn' => 0,
    ]);

    $player = GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => 1,
    ]);

    $this->actingAs($user)->postJson(route('games.setup.scheme', $game->uuid), [
        'scheme_id' => $pool[0]->id,
    ])->assertOk();

    $player->refresh();
    expect($player->current_scheme_id)->toBe($pool[0]->id);
});

it('rejects selecting a scheme not in the pool', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $outsideScheme = Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    $game = Game::factory()->create([
        'creator_id' => $user->id,
        'status' => GameStatusEnum::SchemeSelect,
        'strategy_id' => $strategy->id,
        'scheme_pool' => collect($pool)->pluck('id')->toArray(),
        'current_turn' => 0,
    ]);

    GamePlayer::factory()->create([
        'game_id' => $game->id,
        'user_id' => $user->id,
        'slot' => 1,
    ]);

    $this->actingAs($user)->postJson(route('games.setup.scheme', $game->uuid), [
        'scheme_id' => $outsideScheme->id,
    ])->assertStatus(422);
});

// ─── Turn Submission: Scheme Recording ───

it('records current scheme on the turn even when scoring 0', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game, ['strategy_points' => 1, 'scheme_points' => 0])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_id)->toBe($pool[0]->id);
});

it('records old scheme on turn then updates to next scheme', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $nextSchemeId = $pool[0]->next_scheme_one_id;

    submitTurn($this, $user1, $game, [
        'scheme_points' => 1,
        'next_scheme_id' => $nextSchemeId,
    ])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_id)->toBe($pool[0]->id); // Old scheme on the turn

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($nextSchemeId); // New scheme for next turn
});

it('allows switching to a next-scheme chain scheme', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $nextSchemeId = $pool[0]->next_scheme_two_id;

    submitTurn($this, $user1, $game, ['next_scheme_id' => $nextSchemeId])->assertOk();

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($nextSchemeId);
});

it('rejects next_scheme_id not in pool or chain', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game] = createDuelGame($pool, $user1, $user2);

    $outsideScheme = Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    submitTurn($this, $user1, $game, ['next_scheme_id' => $outsideScheme->id])->assertStatus(422);
});

it('allows holding scheme with no next_scheme_id', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game)->assertOk();

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($pool[0]->id); // Unchanged
});

// ─── Scheme Notes ───

it('saves and snapshots scheme notes on turn', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    // Save notes
    $this->actingAs($user1)->patchJson(route('games.play.scheme-notes', $game->uuid), [
        'scheme_notes' => [
            'note' => 'Target the henchman',
            'selected_model' => 'Lady Justice',
            'selected_marker' => null,
            'terrain_note' => null,
        ],
    ])->assertOk();

    $p1->refresh();
    expect($p1->scheme_notes['note'])->toBe('Target the henchman');
    expect($p1->scheme_notes['selected_model'])->toBe('Lady Justice');

    // Submit turn — notes should be snapshotted
    submitTurn($this, $user1, $game, ['strategy_points' => 1])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_notes['note'])->toBe('Target the henchman');
    expect($turn->scheme_notes['selected_model'])->toBe('Lady Justice');
});

// ─── Solo Mode: Opponent Scheme ───

it('sets opponent scheme via solo_scheme_id from pool', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    submitTurn($this, $user, $game, [
        'slot' => 2,
        'scheme_points' => 1,
        'solo_scheme_id' => $pool[1]->id,
    ])->assertOk();

    $p2->refresh();
    expect($p2->current_scheme_id)->toBe($pool[1]->id);

    $turn = GameTurn::where('game_player_id', $p2->id)->first();
    expect($turn->scheme_id)->toBe($pool[1]->id);
});

it('rejects solo_scheme_id not in pool or chain', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game] = createSoloGame($pool, $user);

    $outsideScheme = Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    submitTurn($this, $user, $game, [
        'slot' => 2,
        'solo_scheme_id' => $outsideScheme->id,
    ])->assertStatus(422);
});

it('allows opponent to hold scheme hidden (no solo_scheme_id)', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    submitTurn($this, $user, $game, ['slot' => 2])->assertOk();

    $p2->refresh();
    expect($p2->current_scheme_id)->toBeNull(); // Still hidden

    $turn = GameTurn::where('game_player_id', $p2->id)->first();
    expect($turn->scheme_id)->toBeNull(); // Not recorded
});

it('sets opponent scheme then advances to follow-up in same request', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    $followUpId = $pool[1]->next_scheme_one_id;

    submitTurn($this, $user, $game, [
        'slot' => 2,
        'scheme_points' => 2,
        'solo_scheme_id' => $pool[1]->id,
        'next_scheme_id' => $followUpId,
    ])->assertOk();

    $p2->refresh();
    // current_scheme_id should be the follow-up (not the scored scheme)
    expect($p2->current_scheme_id)->toBe($followUpId);

    $turn = GameTurn::where('game_player_id', $p2->id)->first();
    // Turn should record the scored scheme
    expect($turn->scheme_id)->toBe($pool[1]->id);
    expect($turn->scheme_points)->toBe(2);
});

it('rejects opponent follow-up not in identified schemes chain', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game] = createSoloGame($pool, $user);

    // pool[2]'s follow-ups are NOT valid from pool[1]
    $wrongFollowUp = $pool[2]->next_scheme_one_id;

    submitTurn($this, $user, $game, [
        'slot' => 2,
        'solo_scheme_id' => $pool[1]->id,
        'next_scheme_id' => $wrongFollowUp,
    ])->assertStatus(422);
});

// ─── Scheme Scoring Limits ───

it('enforces scheme max 2 per turn', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game, ['scheme_points' => 3])->assertStatus(422);
});

it('enforces scheme max 6 total across game', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    // Create 3 previous turns with 2 scheme points each = 6 total
    for ($t = 1; $t <= 3; $t++) {
        GameTurn::create([
            'game_id' => $game->id,
            'game_player_id' => $p1->id,
            'turn_number' => $t,
            'scheme_id' => $pool[0]->id,
            'strategy_points' => 0,
            'scheme_points' => 2,
            'points_scored' => 2,
        ]);
    }
    $game->update(['current_turn' => 4]);

    submitTurn($this, $user1, $game, ['scheme_points' => 1])->assertStatus(422);
});

// ─── Turn Advancement ───

it('advances turn when both players submit in duel', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game)->assertOk();
    $game->refresh();
    expect($game->current_turn)->toBe(1);

    submitTurn($this, $user2, $game)->assertOk();
    $game->refresh();
    expect($game->current_turn)->toBe(2);
});

it('advances turn when both slots submit in solo', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game] = createSoloGame($pool, $user);

    submitTurn($this, $user, $game)->assertOk(); // Player turn
    $game->refresh();
    expect($game->current_turn)->toBe(1);

    submitTurn($this, $user, $game, ['slot' => 2])->assertOk(); // Opponent turn
    $game->refresh();
    expect($game->current_turn)->toBe(2);
});

// ─── Game Completion ───

it('finalizes game and records winner', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1, 'p2' => $p2] = createDuelGame($pool, $user1, $user2);

    $p1->update(['total_points' => 5]);
    $p2->update(['total_points' => 3]);

    $this->actingAs($user1)->postJson(route('games.play.complete', $game->uuid))->assertOk();
    $this->actingAs($user2)->postJson(route('games.play.complete', $game->uuid))->assertOk();

    $game->refresh();
    expect($game->status)->toBe(GameStatusEnum::Completed);
    expect($game->winner_id)->toBe($user1->id);
});

it('records final scheme on completion turn', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1, 'p2' => $p2] = createDuelGame($pool, $user1, $user2);

    $this->actingAs($user1)->postJson(route('games.play.complete', $game->uuid))->assertOk();
    $this->actingAs($user2)->postJson(route('games.play.complete', $game->uuid))->assertOk();

    // Final turn should have the player's scheme recorded
    $finalTurn = GameTurn::where('game_id', $game->id)
        ->where('game_player_id', $p1->id)
        ->where('turn_number', $game->current_turn)
        ->first();

    expect($finalTurn)->not->toBeNull();
    expect($finalTurn->scheme_id)->toBe($pool[0]->id);
});
