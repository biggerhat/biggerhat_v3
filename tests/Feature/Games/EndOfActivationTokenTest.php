<?php

use App\Enums\TokenRemovalTimingEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Token;
use App\Models\User;

/**
 * @return array{0: User, 1: Game, 2: GameCrewMember, 3: Token, 4: Token}
 */
function endOfActivationFixture(bool $alreadyActivated = false): array
{
    $focus = Token::factory()->create(['name' => 'Focus', 'removal_timing' => TokenRemovalTimingEnum::EndOfActivation]);
    $burning = Token::factory()->create(['name' => 'Burning']); // persists (null timing)

    $user = User::factory()->create();
    $game = Game::factory()->bonanza()->inProgress()->create(['creator_id' => $user->id]);
    $player = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $user->id, 'slot' => 1]);
    $member = GameCrewMember::factory()->create([
        'game_id' => $game->id,
        'game_player_id' => $player->id,
        'is_activated' => $alreadyActivated,
        'attached_tokens' => [['id' => $focus->id, 'name' => 'Focus'], ['id' => $burning->id, 'name' => 'Burning']],
    ]);

    return [$user, $game, $member, $focus, $burning];
}

it('drops end-of-activation tokens when a model is activated and reports them', function () {
    [$user, $game, $member, $focus] = endOfActivationFixture();

    $resp = $this->actingAs($user)
        ->patchJson(route('games.play.crew.update', ['game' => $game->uuid, 'gameCrewMember' => $member->id]), ['is_activated' => true])
        ->assertOk();

    $names = collect($member->fresh()->attached_tokens)->pluck('name');
    expect($names)->toContain('Burning')->not->toContain('Focus');
    $resp->assertJsonPath('removed_tokens.0.id', $focus->id)
        ->assertJsonPath('removed_tokens.0.name', 'Focus');
});

it('does not drop end-of-activation tokens when de-activating', function () {
    [$user, $game, $member] = endOfActivationFixture(alreadyActivated: true);

    $this->actingAs($user)
        ->patchJson(route('games.play.crew.update', ['game' => $game->uuid, 'gameCrewMember' => $member->id]), ['is_activated' => false])
        ->assertOk()
        ->assertJsonPath('removed_tokens', []);

    expect(collect($member->fresh()->attached_tokens)->pluck('name'))->toContain('Focus');
});

it('leaves persistent tokens alone on activation', function () {
    [$user, $game, $member, , $burning] = endOfActivationFixture();

    $this->actingAs($user)
        ->patchJson(route('games.play.crew.update', ['game' => $game->uuid, 'gameCrewMember' => $member->id]), ['is_activated' => true])
        ->assertOk();

    expect(collect($member->fresh()->attached_tokens)->pluck('id'))->toContain($burning->id);
});
