<?php

use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Token;
use App\Models\User;

it('bulk-attaches a token to the selected living crew members only', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id, 'is_solo' => true]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);

    $m1 = GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $player->id, 'attached_tokens' => []]);
    $m2 = GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $player->id, 'attached_tokens' => []]);
    $dead = GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $player->id, 'is_killed' => true, 'attached_tokens' => []]);
    $token = Token::factory()->create(['name' => 'Focus']);

    $this->actingAs($user)->postJson(route('games.play.crew.tokens.bulk', $game->uuid), [
        'token_id' => $token->id,
        'member_ids' => [$m1->id, $m2->id, $dead->id],
    ])->assertOk();

    expect(collect($m1->fresh()->attached_tokens)->pluck('name'))->toContain('Focus')
        ->and(collect($m2->fresh()->attached_tokens)->pluck('name'))->toContain('Focus')
        ->and($dead->fresh()->attached_tokens)->toBeEmpty(); // killed model skipped
});

it('does not duplicate a token a model already has', function () {
    $user = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $user->id, 'is_solo' => true]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $token = Token::factory()->create(['name' => 'Focus']);
    $m = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
        'attached_tokens' => [['id' => $token->id, 'name' => 'Focus']],
    ]);

    $this->actingAs($user)->postJson(route('games.play.crew.tokens.bulk', $game->uuid), [
        'token_id' => $token->id,
        'member_ids' => [$m->id],
    ])->assertOk();

    expect($m->fresh()->attached_tokens)->toHaveCount(1);
});
