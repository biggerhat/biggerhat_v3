<?php

use App\Enums\GamePlayModeEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\User;
use App\Support\Game\GameDeployment;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Feature::for(null)->activate('experiment-tabletop');
});

/**
 * A tabletop game at the Deployment step: slot 1 is the attacker, slot 2 the defender, the defender with three models.
 *
 * @return array{0: Game, 1: User, 2: User, 3: list<int>, 4: list<int>} the game, attacker, defender, defender model ids, attacker model ids
 */
function deploymentGame(int $defenderModels = 3, int $attackerModels = 2, bool $solo = false): array
{
    $attacker = User::factory()->create();
    $defender = User::factory()->create();
    $game = Game::factory()->create([
        'creator_id' => $attacker->id,
        'is_solo' => $solo,
        'status' => GameStatusEnum::Deployment,
        'play_mode' => GamePlayModeEnum::Tabletop,
    ]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $attacker->id, 'slot' => 1, 'role' => GameRoleEnum::Attacker]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $solo ? null : $defender->id, 'slot' => 2, 'role' => GameRoleEnum::Defender]);
    $mine = GameCrewMember::factory()->count($attackerModels)->create(['game_id' => $game->id, 'game_player_id' => $p1->id])->pluck('id')->all();
    $theirs = GameCrewMember::factory()->count($defenderModels)->create(['game_id' => $game->id, 'game_player_id' => $p2->id])->pluck('id')->all();

    return [$game, $attacker, $defender, $theirs, $mine];
}

/** @param list<int> $ids */
function at(array $ids, float $x = 6, float $y = 3): array
{
    return ['positions' => collect($ids)->mapWithKeys(fn ($id, $i) => [$id => ['x' => $x + $i * 2, 'y' => $y]])->all()];
}

it('runs the rulebook order: defender splits, attacker chooses, defender places, attacker places, defender places the rest', function () {
    [$game, $attacker, $defender, $theirs, $mine] = deploymentGame();
    [$first, $second, $third] = $theirs;

    expect(GameDeployment::summary($game->fresh()))->phase->toBe('split')->actor_slot->toBe(2);

    // only the defender may divide the crew
    $this->actingAs($attacker)->post(route('games.deploy.split', $game->uuid), ['later' => [$third]])->assertForbidden();
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => [$third]])->assertRedirect();
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('choose')->actor_slot->toBe(1)->later->toBe([$third]);

    // the attacker chooses the group held back to go first
    $this->actingAs($defender)->post(route('games.deploy.choose', $game->uuid), ['first' => 'later'])->assertForbidden();
    $this->actingAs($attacker)->post(route('games.deploy.choose', $game->uuid), ['first' => 'later'])->assertRedirect();
    $summary = GameDeployment::summary($game->fresh());
    expect($summary)->phase->toBe('first')->actor_slot->toBe(2)->placing->toBe([$third]);

    $this->actingAs($defender)->post(route('games.deploy.done', $game->uuid), at([$third]))->assertRedirect();
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('attacker')->actor_slot->toBe(1)->placing->toBe($mine);

    $this->actingAs($attacker)->post(route('games.deploy.done', $game->uuid), at($mine, 6, 30))->assertRedirect();
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('second')->placing->toBe([$first, $second]);

    $this->actingAs($defender)->post(route('games.deploy.done', $game->uuid), at([$first, $second]))->assertRedirect();

    // deployment is over: schemes are next, and every model's place was kept
    $game = $game->fresh();
    expect($game->status)->toBe(GameStatusEnum::SchemeSelect)
        ->and(array_keys($game->board_state['positions']))->toHaveCount(5);
});

it('skips the choice when the defender keeps the crew together, or sends it all later', function () {
    [$game, , $defender, $theirs] = deploymentGame();
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => []])->assertRedirect();
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('first')->placing->toBe($theirs);

    [$game, , $defender, $theirs] = deploymentGame();
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => $theirs])->assertRedirect();
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('first')->placing->toBe($theirs);
});

it('goes past a side that has nothing to place', function () {
    [$game, , , , $mine] = deploymentGame(defenderModels: 0);

    expect(GameDeployment::summary($game->fresh()))->phase->toBe('attacker')->placing->toBe($mine);
});

it('refuses an action in the wrong phase, models that are not the group, and an unfinished group', function () {
    [$game, $attacker, $defender, $theirs, $mine] = deploymentGame();

    $this->actingAs($defender)->post(route('games.deploy.done', $game->uuid), at($theirs))->assertRedirect()->assertSessionHas('messageType', 'error');
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => $mine])->assertRedirect()->assertSessionHas('messageType', 'error');
    expect(GameDeployment::summary($game->fresh()))->phase->toBe('split');

    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => []]);
    $this->actingAs($defender)->post(route('games.deploy.done', $game->uuid), at([$theirs[0]]))->assertRedirect()->assertSessionHas('messageType', 'error');
    expect($game->fresh()->status)->toBe(GameStatusEnum::Deployment);
});

it('lets only the group being placed be moved, by the player placing it', function () {
    [$game, $attacker, $defender, $theirs, $mine] = deploymentGame();
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => []]);
    $move = fn (User $u, array $ids) => $this->actingAs($u)->put(route('games.board.update', $game->uuid), at($ids));

    // the defender is placing: their models move, the attacker's do not (even their own)
    $move($defender, $theirs)->assertRedirect();
    $move($attacker, $mine)->assertForbidden();
    $move($defender, $mine)->assertForbidden();
    expect($game->fresh()->board_state['positions'])->toHaveCount(3);
});

it('does not restrict moving once deployment is over', function () {
    [$game, $attacker, , , $mine] = deploymentGame();
    $game->update(['status' => GameStatusEnum::InProgress]);

    $this->actingAs($attacker)->put(route('games.board.update', $game->uuid), at($mine))->assertRedirect();
});

it('ends deployment when every player says to start without deploying', function () {
    [$game, $attacker, $defender] = deploymentGame();

    $this->actingAs($attacker)->post(route('games.deploy.skip', $game->uuid))->assertRedirect();
    expect($game->fresh()->status)->toBe(GameStatusEnum::Deployment);
    $this->actingAs($defender)->post(route('games.deploy.skip', $game->uuid))->assertRedirect();
    expect($game->fresh()->status)->toBe(GameStatusEnum::SchemeSelect);
});

it('lets one person run both sides of a solo game', function () {
    [$game, $user, , $theirs, $mine] = deploymentGame(solo: true);

    $this->actingAs($user)->post(route('games.deploy.split', $game->uuid), ['later' => []])->assertRedirect();
    $this->actingAs($user)->post(route('games.deploy.done', $game->uuid), at($theirs))->assertRedirect();
    $this->actingAs($user)->post(route('games.deploy.done', $game->uuid), at($mine, 6, 30))->assertRedirect();

    expect($game->fresh()->status)->toBe(GameStatusEnum::SchemeSelect);

    [$game, $user] = deploymentGame(solo: true);
    $this->actingAs($user)->post(route('games.deploy.skip', $game->uuid))->assertRedirect();
    expect($game->fresh()->status)->toBe(GameStatusEnum::SchemeSelect);
});

it('changes nothing for a request that arrives after deployment is over', function () {
    [$game, , $defender] = deploymentGame();
    $game->update(['status' => GameStatusEnum::SchemeSelect]);

    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => []])->assertRedirect()->assertSessionHas('messageType', 'error');
    expect($game->fresh()->status)->toBe(GameStatusEnum::SchemeSelect);
});

it('keeps deployment to people in the game, and to those who have the tabletop', function () {
    [$game, , $defender] = deploymentGame();

    $this->actingAs(User::factory()->create())->post(route('games.deploy.split', $game->uuid), ['later' => []])->assertForbidden();

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => []])->assertNotFound();
});

it('validates what the player sends', function () {
    [$game, $attacker, $defender, $theirs] = deploymentGame();

    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), [])->assertSessionHasErrors('later');
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => ['x']])->assertSessionHasErrors('later.0');
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => [$theirs[0]]]);
    $this->actingAs($attacker)->post(route('games.deploy.choose', $game->uuid), ['first' => 'soon'])->assertSessionHasErrors('first');
    $this->actingAs($attacker)->post(route('games.deploy.choose', $game->uuid), ['first' => 'now']);
    $this->actingAs($defender)->post(route('games.deploy.done', $game->uuid), ['positions' => [$theirs[1] => ['x' => 99, 'y' => 1]]])->assertSessionHasErrors('positions.'.$theirs[1].'.x');
});

it('puts a tabletop game on a Deployment step between crew and scheme, and the board and play screen say where it stands', function () {
    [$game, $attacker] = deploymentGame();

    $this->actingAs($attacker)->get(route('games.show', $game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('setup_steps.3.key', 'deploy')
        ->where('deploy.phase', 'split'));
    $this->actingAs($attacker)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.status', 'deployment')
        ->where('deploy.actor_slot', 2));
});

it('lets either player pick the deployment type before anyone has placed a model, and says so to the board', function () {
    [$game, $attacker, $defender, $theirs] = deploymentGame();
    $game->update(['deployment' => 'standard', 'board_state' => ['deployment_variant' => 2]]);

    // the attacker, before the defender has split the crew; the zones go back to their first position
    $this->actingAs($attacker)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'corner'])->assertRedirect();
    $fresh = $game->fresh();
    expect($fresh->deployment->value)->toBe('corner')
        ->and($fresh->board_state['deployment_variant'])->toBe(0)
        ->and(GameDeployment::summary($fresh)['deployment'])->toBe('corner');

    // the defender may too, including after splitting and while the attacker is choosing
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => [$theirs[2]]])->assertRedirect();
    $this->actingAs($defender)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'wedge'])->assertRedirect();
    expect($game->fresh()->deployment->value)->toBe('wedge');
});

it('fixes the deployment type once a crew is being placed, and refuses anything but a real type', function () {
    [$game, $attacker, $defender, $theirs] = deploymentGame();
    $game->update(['deployment' => 'standard']);
    $this->actingAs($defender)->post(route('games.deploy.split', $game->uuid), ['later' => [$theirs[2]]])->assertRedirect();
    $this->actingAs($attacker)->post(route('games.deploy.choose', $game->uuid), ['first' => 'later'])->assertRedirect();

    // the first group is being placed: the zones are fixed
    $this->actingAs($attacker)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'flank'])->assertRedirect();
    expect($game->fresh()->deployment->value)->toBe('standard');

    $this->actingAs($attacker)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'nonsense'])->assertSessionHasErrors('deployment');
    $this->actingAs($attacker)->post(route('games.deploy.type', $game->uuid), [])->assertSessionHasErrors('deployment');
});

it('keeps the deployment type to people in the game and to those who have the tabletop', function () {
    [$game] = deploymentGame();
    $game->update(['deployment' => 'standard']);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'flank'])->assertForbidden();

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($game->creator)->post(route('games.deploy.type', $game->uuid), ['deployment' => 'flank'])->assertNotFound();
    expect($game->fresh()->deployment->value)->toBe('standard');
});
