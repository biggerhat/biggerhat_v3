<?php

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\User;

/*
 * Per-rule rejection cases for the game-play FormRequests. Validation runs
 * before the controller body, so a participant sending a bad payload gets a
 * 422 naming the field regardless of the game's state.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->game = Game::factory()->inProgress()->create(['creator_id' => $this->user->id]);
    GamePlayer::factory()->create(['game_id' => $this->game->id, 'user_id' => $this->user->id, 'slot' => 1]);
});

it('rejects invalid game-play payloads', function (string $method, string $routeName, array $payload, string $field) {
    $this->actingAs($this->user)
        ->json($method, route($routeName, $this->game->uuid), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'tokens bulk: no token' => ['POST', 'games.play.crew.tokens.bulk', ['member_ids' => [1]], 'token_id'],
    'tokens bulk: unknown token' => ['POST', 'games.play.crew.tokens.bulk', ['token_id' => 999999, 'member_ids' => [1]], 'token_id'],
    'tokens bulk: no members' => ['POST', 'games.play.crew.tokens.bulk', ['token_id' => 1, 'member_ids' => []], 'member_ids'],
    'tokens bulk: non-integer member' => ['POST', 'games.play.crew.tokens.bulk', ['token_id' => 1, 'member_ids' => ['x']], 'member_ids.0'],
    'tokens restore: empty' => ['POST', 'games.play.crew.tokens.restore', ['tokens' => []], 'tokens'],
    'tokens restore: missing token_id' => ['POST', 'games.play.crew.tokens.restore', ['tokens' => [['member_id' => 1]]], 'tokens.0.token_id'],
    'tokens restore: missing member_id' => ['POST', 'games.play.crew.tokens.restore', ['tokens' => [['token_id' => 1]]], 'tokens.0.member_id'],
    'bonanza vp: no delta' => ['PATCH', 'games.play.bonanza_vp', [], 'delta'],
    'bonanza vp: delta too high' => ['PATCH', 'games.play.bonanza_vp', ['delta' => 21], 'delta'],
    'bonanza vp: delta too low' => ['PATCH', 'games.play.bonanza_vp', ['delta' => -21], 'delta'],
    'bonanza vp: bad slot' => ['PATCH', 'games.play.bonanza_vp', ['delta' => 1, 'slot' => 3], 'slot'],
    'loot select: unknown member' => ['POST', 'games.play.loot.select', ['game_crew_member_id' => 999999, 'loot_card_id' => 1, 'side' => 'a'], 'game_crew_member_id'],
    'loot select: unknown card' => ['POST', 'games.play.loot.select', ['game_crew_member_id' => 1, 'loot_card_id' => 999999, 'side' => 'a'], 'loot_card_id'],
    'loot select: bad side' => ['POST', 'games.play.loot.select', ['game_crew_member_id' => 1, 'loot_card_id' => 1, 'side' => 'c'], 'side'],
    'loot attach: missing side' => ['POST', 'games.play.loot.attach', ['game_crew_member_id' => 1, 'loot_card_id' => 1], 'side'],
    'loot attach: unknown card' => ['POST', 'games.play.loot.attach', ['game_crew_member_id' => 1, 'loot_card_id' => 999999, 'side' => 'a'], 'loot_card_id'],
    'loot yoink: missing marker' => ['POST', 'games.play.loot.yoink', ['game_crew_member_id' => 1, 'side' => 'a'], 'marker_id'],
    'loot yoink: bad side' => ['POST', 'games.play.loot.yoink', ['game_crew_member_id' => 1, 'marker_id' => 'm', 'side' => 'z'], 'side'],
]);

it('rejects out-of-range turn score edits', function (array $payload, string $field) {
    $player = GamePlayer::where('game_id', $this->game->id)->first();
    $turn = GameTurn::create(['game_id' => $this->game->id, 'game_player_id' => $player->id, 'turn_number' => 1]);

    $this->actingAs($this->user)
        ->patchJson(route('games.play.turns.edit', [$this->game->uuid, $turn->id]), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'strategy too high' => [['strategy_points' => 3, 'scheme_points' => 0], 'strategy_points'],
    'strategy negative' => [['strategy_points' => -1, 'scheme_points' => 0], 'strategy_points'],
    'scheme too high' => [['strategy_points' => 0, 'scheme_points' => 4], 'scheme_points'],
    'scheme missing' => [['strategy_points' => 0], 'scheme_points'],
]);
