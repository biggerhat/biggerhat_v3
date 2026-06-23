<?php

use App\Enums\TokenRemovalTimingEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Token;
use App\Models\User;

it('removes end-of-turn tokens on turn advance and reports them for undo', function () {
    $fast = Token::factory()->create(['name' => 'Fast', 'removal_timing' => TokenRemovalTimingEnum::EndOfTurn]);
    $burning = Token::factory()->create(['name' => 'Burning']); // persists (null timing)

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create([
        'creator_id' => $user->id,
        'current_turn' => 1,
        'max_turns' => 5,
    ]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
        'attached_tokens' => [['id' => $fast->id, 'name' => 'Fast'], ['id' => $burning->id, 'name' => 'Burning']],
    ]);

    $resp = $this->actingAs($user)->postJson(route('games.play.bonanza_next_turn', $game->uuid))->assertOk();

    $names = collect($member->fresh()->attached_tokens)->pluck('name');
    expect($names)->toContain('Burning')->not->toContain('Fast');
    $resp->assertJsonPath('removed_tokens.0.token_name', 'Fast');
});

it('restores removed tokens via the restore endpoint (Undo)', function () {
    $fast = Token::factory()->create(['name' => 'Fast', 'removal_timing' => TokenRemovalTimingEnum::EndOfTurn]);
    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create(['creator_id' => $user->id]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $player->id, 'attached_tokens' => []]);

    $this->actingAs($user)->postJson(route('games.play.crew.tokens.restore', $game->uuid), [
        'tokens' => [['member_id' => $member->id, 'token_id' => $fast->id, 'token_name' => 'Fast']],
    ])->assertOk();

    expect(collect($member->fresh()->attached_tokens)->pluck('name'))->toContain('Fast');
});
