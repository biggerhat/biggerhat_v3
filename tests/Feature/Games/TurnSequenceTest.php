<?php

use App\Enums\GameRoleEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\User;
use App\Services\Game\TurnSequence;

/**
 * A two-player game in progress: slot 1 is the attacker (user $a) with $mine models, slot 2 the defender (user $b) with $theirs.
 *
 * @return array{0: Game, 1: GamePlayer, 2: GamePlayer, 3: User, 4: User, 5: list<GameCrewMember>, 6: list<GameCrewMember>}
 */
function turnGame(int $mine = 3, int $theirs = 3): array
{
    $a = User::factory()->create();
    $b = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_solo' => false, 'current_turn' => 1, 'max_turns' => 4]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1, 'role' => GameRoleEnum::Attacker]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $b->id, 'slot' => 2, 'role' => GameRoleEnum::Defender]);
    $make = fn (GamePlayer $p, int $n) => collect(range(1, $n))->map(fn () => GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $p->id]))->all();

    return [$game, $p1, $p2, $a, $b, $make($p1, $mine), $make($p2, $theirs)];
}

function activate(User $user, Game $game, GameCrewMember $member): void
{
    test()->actingAs($user)->patchJson(route('games.play.crew.update', [$game->uuid, $member->id]), ['is_activated' => true])->assertOk();
}

it('gives the attacker initiative on turn 1, with the first activation', function () {
    [$game] = turnGame(3, 2);

    $state = app(TurnSequence::class)->state($game);

    expect($state['turn'])->toBe(1)->and($state['initiative'])->toBe(1)->and($state['active'])->toBe(1)->and($state['chooser'])->toBeNull()
        ->and($state['unactivated'])->toBe([1 => 3, 2 => 2]);
});

it('has nothing to say about a game that is not in progress', function () {
    [$game] = turnGame();
    $game->update(['status' => 'setup']);

    expect(app(TurnSequence::class)->state($game))->toBeNull();
});

it('hands the activation to the other player when a model activates', function () {
    [$game, , , $a, $b, $mine, $theirs] = turnGame(3, 3);

    activate($a, $game, $mine[0]);
    expect(app(TurnSequence::class)->state($game->refresh())['active'])->toBe(2)
        ->and(app(TurnSequence::class)->state($game)['unactivated'])->toBe([1 => 2, 2 => 3]);

    activate($b, $game, $theirs[0]);
    expect(app(TurnSequence::class)->state($game->refresh())['active'])->toBe(1);
});

it('keeps the activation with a player when the other has nobody left', function () {
    [$game, , , $a, , $mine, $theirs] = turnGame(3, 1);
    GameCrewMember::query()->whereKey($theirs[0]->id)->update(['is_activated' => true]);

    activate($a, $game, $mine[0]);

    expect(app(TurnSequence::class)->state($game->refresh())['active'])->toBe(1);
});

it('lets the active player pass only with no more models left to activate than the opponent, and not straight back', function () {
    [$game, , , $a, $b, $mine, $theirs] = turnGame(3, 3);
    $url = route('games.play.turn.pass', $game->uuid);

    // tied for fewest: slot 1 is active and may pass
    expect(app(TurnSequence::class)->state($game)['can_pass'])->toBe([1 => true, 2 => true]);
    $this->actingAs($a)->postJson($url)->assertOk();
    expect($game->refresh()->active_slot)->toBe(2)->and($game->passed_slot)->toBe(1);
    // the opponent may not pass straight back (and the note says why)
    $state = app(TurnSequence::class)->state($game);
    expect($state['can_pass'][2])->toBeFalse()->and($state['pass_note'])->toContain('just passed');
    $this->actingAs($b)->postJson($url)->assertUnprocessable();

    // an activation clears it
    activate($b, $game, $theirs[0]);
    expect($game->refresh()->passed_slot)->toBeNull();
});

it('refuses a pass with more models left than the opponent, and out of turn', function () {
    [$game, , , $a, $b, $mine] = turnGame(4, 2);
    $url = route('games.play.turn.pass', $game->uuid);

    $this->actingAs($a)->postJson($url)->assertUnprocessable()->assertJsonPath('error', 'You have more models left to activate than your opponent, so you may not pass.');
    $this->actingAs($b)->postJson($url)->assertUnprocessable()->assertJsonPath('error', 'It is not your activation.');
});

it('has the player who did not activate last choose initiative from turn 2, and only they', function () {
    [$game, , , $a, $b, $mine, $theirs] = turnGame(1, 1);
    // slot 1 activates the last model of turn 1; slot 2 had already activated
    GameCrewMember::query()->whereKey($theirs[0]->id)->update(['is_activated' => true]);
    activate($a, $game, $mine[0]);
    $score = ['strategy_points' => 0, 'scheme_points' => 0, 'scheme_action' => 'held'];
    $this->actingAs($a)->postJson(route('games.play.turns.store', $game->uuid), $score)->assertOk();
    $this->actingAs($b)->postJson(route('games.play.turns.store', $game->uuid), $score)->assertOk();
    $game->refresh();

    $state = app(TurnSequence::class)->state($game);
    expect($game->current_turn)->toBe(2)->and($state['initiative'])->toBeNull()->and($state['active'])->toBeNull()
        ->and($state['chooser'])->toBe(2); // slot 1 activated last, so slot 2 chooses

    $url = route('games.play.turn.initiative', $game->uuid);
    $this->actingAs($a)->postJson($url, ['initiative' => 1])->assertUnprocessable();
    $this->actingAs($b)->postJson($url, ['initiative' => 9])->assertUnprocessable();
    $this->actingAs($b)->postJson($url, ['initiative' => 2])->assertOk()->assertJsonPath('turn.initiative', 2)->assertJsonPath('turn.active', 2);
    $this->actingAs($b)->postJson($url, ['initiative' => 1])->assertUnprocessable(); // already decided
});

it('lets the solo creator act for either slot', function () {
    $a = User::factory()->create();
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_solo' => true, 'current_turn' => 1]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1, 'role' => GameRoleEnum::Attacker]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => null, 'slot' => 2, 'role' => GameRoleEnum::Defender]);
    GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $p1->id]);
    GameCrewMember::factory()->create(['game_id' => $game->id, 'game_player_id' => $p2->id]);

    $this->actingAs($a)->postJson(route('games.play.turn.pass', $game->uuid), ['slot' => 1])->assertOk();
    $this->actingAs($a)->postJson(route('games.play.turn.pass', $game->uuid), ['slot' => 2])->assertUnprocessable(); // passed straight back
    expect($game->refresh()->active_slot)->toBe(2);
});

it('rides along in the game payload while in progress', function () {
    [$game, , , $a] = turnGame(2, 2);

    $this->actingAs($a)->get(route('games.show', $game->uuid))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('game.turn_sequence.initiative', 1)->where('game.turn_sequence.unactivated.1', 2));
});

it('refuses outsiders and signed-out people', function () {
    [$game] = turnGame();

    $this->actingAs(User::factory()->create())->postJson(route('games.play.turn.pass', $game->uuid))->assertForbidden();
    $this->postJson(route('games.play.turn.initiative', $game->uuid), ['initiative' => 1])->assertForbidden();
});
