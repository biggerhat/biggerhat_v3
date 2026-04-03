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
    $poolIds = collect($pool)->pluck('id')->toArray();
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user1->id,
        'strategy_id' => $strategy->id,
        'scheme_pool' => $poolIds,
    ]);

    // Each player picks a scheme — pool becomes that scheme's follow-ups
    $p1Pool = array_values(array_filter([$pool[0]->next_scheme_one_id, $pool[0]->next_scheme_two_id, $pool[0]->next_scheme_three_id]));
    $p2Pool = array_values(array_filter([$pool[1]->next_scheme_one_id, $pool[1]->next_scheme_two_id, $pool[1]->next_scheme_three_id]));

    $p1 = GamePlayer::factory()->create([
        'game_id' => $game->id, 'user_id' => $user1->id, 'slot' => 1,
        'current_scheme_id' => $pool[0]->id, 'scheme_pool' => $p1Pool,
    ]);
    $p2 = GamePlayer::factory()->create([
        'game_id' => $game->id, 'user_id' => $user2->id, 'slot' => 2,
        'current_scheme_id' => $pool[1]->id, 'scheme_pool' => $p2Pool,
    ]);

    return ['game' => $game, 'p1' => $p1, 'p2' => $p2];
}

function createSoloGame(array $pool, User $user): array
{
    $strategy = Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $poolIds = collect($pool)->pluck('id')->toArray();
    $game = Game::factory()->inProgress()->create([
        'creator_id' => $user->id,
        'strategy_id' => $strategy->id,
        'scheme_pool' => $poolIds,
        'is_solo' => true,
    ]);

    $p1Pool = array_values(array_filter([$pool[0]->next_scheme_one_id, $pool[0]->next_scheme_two_id, $pool[0]->next_scheme_three_id]));

    $p1 = GamePlayer::factory()->create([
        'game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1,
        'current_scheme_id' => $pool[0]->id, 'scheme_pool' => $p1Pool,
    ]);
    $p2 = GamePlayer::factory()->create([
        'game_id' => $game->id, 'user_id' => null, 'slot' => 2,
        'current_scheme_id' => null, 'scheme_pool' => $poolIds,
    ]);

    return ['game' => $game, 'p1' => $p1, 'p2' => $p2];
}

function submitTurn($test, User $user, Game $game, array $data = []): \Illuminate\Testing\TestResponse
{
    return $test->actingAs($user)->postJson(
        route('games.play.turns.store', $game->uuid),
        array_merge(['strategy_points' => 0, 'scheme_points' => 0, 'scheme_action' => 'held'], $data)
    );
}

// ─── Scheme Selection (Setup Phase) ───

it('sets scheme_pool when selecting initial scheme', function () {
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
        'game_id' => $game->id, 'user_id' => $user->id, 'slot' => 1,
    ]);

    $this->actingAs($user)->postJson(route('games.setup.scheme', $game->uuid), [
        'scheme_id' => $pool[0]->id,
    ])->assertOk();

    $player->refresh();
    expect($player->current_scheme_id)->toBe($pool[0]->id);
    // scheme_pool should be the chosen scheme's follow-up chain
    $expectedPool = array_values(array_filter([
        $pool[0]->next_scheme_one_id, $pool[0]->next_scheme_two_id, $pool[0]->next_scheme_three_id,
    ]));
    expect($player->scheme_pool)->toBe($expectedPool);
});

// ─── Turn Submission: scheme_action stored explicitly ───

it('records scheme_action as held when holding', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game, ['scheme_action' => 'held'])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_action)->toBe('held');
    expect($turn->scheme_id)->toBe($pool[0]->id);

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($pool[0]->id); // Unchanged
});

it('records scheme_action as scored and requires next_scheme_id', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $nextId = $pool[0]->next_scheme_one_id;

    submitTurn($this, $user1, $game, [
        'scheme_points' => 2,
        'scheme_action' => 'scored',
        'next_scheme_id' => $nextId,
    ])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_action)->toBe('scored');
    expect($turn->scheme_id)->toBe($pool[0]->id); // Old scheme on turn
    expect($turn->next_scheme_id)->toBe($nextId);

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($nextId); // Updated to next
});

it('records scheme_action as discarded with next_scheme_id', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $nextId = $pool[0]->next_scheme_two_id;

    submitTurn($this, $user1, $game, [
        'scheme_action' => 'discarded',
        'next_scheme_id' => $nextId,
    ])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_action)->toBe('discarded');
    expect($turn->scheme_id)->toBe($pool[0]->id);

    $p1->refresh();
    expect($p1->current_scheme_id)->toBe($nextId);
});

it('updates scheme_pool when switching schemes', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $nextId = $pool[0]->next_scheme_one_id;
    $nextScheme = Scheme::find($nextId);

    submitTurn($this, $user1, $game, [
        'scheme_points' => 1,
        'scheme_action' => 'scored',
        'next_scheme_id' => $nextId,
    ])->assertOk();

    $p1->refresh();
    $expectedPool = array_values(array_filter([
        $nextScheme->next_scheme_one_id, $nextScheme->next_scheme_two_id, $nextScheme->next_scheme_three_id,
    ]));
    // If next scheme has no follow-ups, pool keeps previous value
    if (! empty($expectedPool)) {
        expect($p1->scheme_pool)->toBe($expectedPool);
    }
});

it('rejects next_scheme_id not in player pool', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game] = createDuelGame($pool, $user1, $user2);

    $outsideScheme = Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    submitTurn($this, $user1, $game, [
        'scheme_action' => 'discarded',
        'next_scheme_id' => $outsideScheme->id,
    ])->assertStatus(422);
});

// ─── Scheme Notes ───

it('snapshots scheme notes on turn and clears on scheme switch', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    $this->actingAs($user1)->patchJson(route('games.play.scheme-notes', $game->uuid), [
        'scheme_notes' => ['note' => 'Test note', 'selected_model' => 'Lady J', 'selected_marker' => null, 'terrain_note' => null],
    ])->assertOk();

    $nextId = $pool[0]->next_scheme_one_id;
    submitTurn($this, $user1, $game, [
        'scheme_points' => 1, 'scheme_action' => 'scored', 'next_scheme_id' => $nextId,
    ])->assertOk();

    $turn = GameTurn::where('game_player_id', $p1->id)->first();
    expect($turn->scheme_notes['note'])->toBe('Test note');

    $p1->refresh();
    expect($p1->scheme_notes)->toBeNull(); // Cleared on switch
});

// ─── Solo Mode ───

it('sets opponent scheme via identified_scheme_id from pool', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    submitTurn($this, $user, $game, [
        'slot' => 2, 'scheme_points' => 1, 'scheme_action' => 'scored',
        'identified_scheme_id' => $pool[1]->id,
    ])->assertOk();

    $p2->refresh();
    expect($p2->current_scheme_id)->toBe($pool[1]->id);

    $turn = GameTurn::where('game_player_id', $p2->id)->first();
    expect($turn->scheme_id)->toBe($pool[1]->id);
    expect($turn->scheme_action)->toBe('scored');
});

it('rejects identified_scheme_id not in opponent pool', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game] = createSoloGame($pool, $user);

    $outsideScheme = Scheme::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    submitTurn($this, $user, $game, [
        'slot' => 2, 'scheme_action' => 'scored',
        'identified_scheme_id' => $outsideScheme->id,
    ])->assertStatus(422);
});

it('holds opponent scheme hidden with no identified_scheme_id', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    submitTurn($this, $user, $game, ['slot' => 2, 'scheme_action' => 'held'])->assertOk();

    $p2->refresh();
    expect($p2->current_scheme_id)->toBeNull();

    $turn = GameTurn::where('game_player_id', $p2->id)->first();
    expect($turn->scheme_action)->toBe('held');
    expect($turn->scheme_id)->toBeNull();
});

it('updates opponent scheme_pool after identifying scored scheme', function () {
    $pool = createSchemeChain();
    $user = User::factory()->create();
    ['game' => $game, 'p2' => $p2] = createSoloGame($pool, $user);

    // Opponent scores on pool[1]
    submitTurn($this, $user, $game, [
        'slot' => 2, 'scheme_points' => 1, 'scheme_action' => 'scored',
        'identified_scheme_id' => $pool[1]->id,
    ])->assertOk();

    $p2->refresh();
    // Pool should now be pool[1]'s follow-up chain
    $expectedPool = array_values(array_filter([
        $pool[1]->next_scheme_one_id, $pool[1]->next_scheme_two_id, $pool[1]->next_scheme_three_id,
    ]));
    expect($p2->scheme_pool)->toBe($expectedPool);
});

// ─── Scoring Limits ───

it('enforces scheme max 2 per turn', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game] = createDuelGame($pool, $user1, $user2);

    submitTurn($this, $user1, $game, ['scheme_points' => 3, 'scheme_action' => 'scored'])->assertStatus(422);
});

it('enforces scheme max 6 total across game', function () {
    $pool = createSchemeChain();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    ['game' => $game, 'p1' => $p1] = createDuelGame($pool, $user1, $user2);

    for ($t = 1; $t <= 3; $t++) {
        GameTurn::create([
            'game_id' => $game->id, 'game_player_id' => $p1->id, 'turn_number' => $t,
            'scheme_id' => $pool[0]->id, 'scheme_action' => 'scored',
            'strategy_points' => 0, 'scheme_points' => 2, 'points_scored' => 2,
        ]);
    }
    $game->update(['current_turn' => 4]);

    submitTurn($this, $user1, $game, ['scheme_points' => 1, 'scheme_action' => 'scored'])->assertStatus(422);
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

    submitTurn($this, $user, $game)->assertOk();
    submitTurn($this, $user, $game, ['slot' => 2])->assertOk();

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
