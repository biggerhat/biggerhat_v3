<?php

use App\Enums\BaseSizeEnum;
use App\Events\GameBoardMoved;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Feature::for(null)->activate('experiment-tabletop');
});

/** A two-player game with one model each: slot 1 is $a, slot 2 is $b. */
function boardGame(User $a, User $b, bool $solo = false): array
{
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_solo' => $solo]);
    $p1 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1]);
    $p2 = GamePlayer::factory()->for($game, 'game')->create(['user_id' => $solo ? null : $b->id, 'slot' => 2]);
    $m1 = GameCrewMember::factory()->create([
        'game_id' => $game->id, 'game_player_id' => $p1->id,
        'character_id' => Character::factory()->create(['base' => BaseSizeEnum::FiftyMM])->id,
    ]);
    $m2 = GameCrewMember::factory()->create([
        'game_id' => $game->id, 'game_player_id' => $p2->id,
        'character_id' => Character::factory()->create(['base' => BaseSizeEnum::ThirtyMM])->id,
    ]);

    return [$game, $m1, $m2];
}

it('is hidden until the flag is on', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertNotFound();
});

it('shows both crews with their real base sizes to a participant', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Board')
            ->has('models', 2)
            ->where('models.0.base_mm', 50)
            ->where('models.1.base_mm', 30)
            ->where('players.0.mine', true)
            ->where('players.1.mine', false));
});

it('keeps the board away from people outside the game', function () {
    [$game] = boardGame(User::factory()->create(), User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route('games.board', $game->uuid))->assertForbidden();
});

it('sends a guest to sign in', function () {
    [$game] = boardGame(User::factory()->create(), User::factory()->create());

    $this->get(route('games.board', $game->uuid))->assertRedirect(route('login'));
});

it('saves the positions of your own models', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10.126, 'y' => 20]]])
        ->assertRedirect();

    expect($game->fresh()->board_state['positions'][(string) $m1->id])->toEqual(['x' => 10.13, 'y' => 20]);
});

it("refuses to move the other player's models", function () {
    [$game, , $m2] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m2->id => ['x' => 5, 'y' => 5]]])
        ->assertForbidden();

    expect($game->fresh()->board_state)->toBeNull();
});

it('drops other players models from a mixed request but saves your own', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3], $m2->id => ['x' => 9, 'y' => 9]]])
        ->assertRedirect();

    $saved = $game->fresh()->board_state['positions'];
    expect($saved)->toHaveKey((string) $m1->id)->not->toHaveKey((string) $m2->id);
});

it('lets each player save without overwriting the other', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), $b = User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3]]]);
    $this->actingAs($b)->put(route('games.board.update', $game->uuid), ['positions' => [$m2->id => ['x' => 30, 'y' => 30]]]);

    expect($game->fresh()->board_state['positions'])->toHaveKeys([(string) $m1->id, (string) $m2->id]);
});

it('lets the only player of a solo game move both sides', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create(), solo: true);

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3], $m2->id => ['x' => 9, 'y' => 9]]])
        ->assertRedirect();

    expect($game->fresh()->board_state['positions'])->toHaveCount(2);
});

it('rejects positions off the table', function (array $position) {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => $position]])
        ->assertSessionHasErrors();
})->with([
    'past the edge' => [['x' => 40, 'y' => 5]],
    'negative' => [['x' => -1, 'y' => 5]],
    'missing y' => [['x' => 5]],
    'not a number' => [['x' => 'abc', 'y' => 5]],
]);

it('refuses saves from outsiders', function () {
    [$game, $m1] = boardGame(User::factory()->create(), User::factory()->create());

    $this->actingAs(User::factory()->create())->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 1, 'y' => 1]]])
        ->assertForbidden();
});

it('reads the base size from a custom card and falls back to 30 mm', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create());
    $m1->update(['character_id' => null, 'custom_character_id' => CustomCharacter::create(['user_id' => $a->id, 'name' => 'C', 'faction' => 'guild', 'health' => 5, 'defense' => 5, 'willpower' => 5, 'speed' => 5, 'base' => 40])->id]);
    $m2->update(['character_id' => null, 'custom_character_id' => null]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('models.0.base_mm', 40)->where('models.1.base_mm', 30));
});

it('tells the game about moves, and only the moves you were allowed to make', function () {
    Event::fake([GameBoardMoved::class]);
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3], $m2->id => ['x' => 9, 'y' => 9]]]);

    Event::assertDispatched(GameBoardMoved::class, fn ($e) => array_keys($e->positions) === [$m1->id] && $e->broadcastOn()[0]->name === 'presence-game.'.$game->uuid);
});

it('broadcasts nothing when a move is refused', function () {
    Event::fake([GameBoardMoved::class]);
    [$game, , $m2] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m2->id => ['x' => 9, 'y' => 9]]])->assertForbidden();

    Event::assertNotDispatched(GameBoardMoved::class);
});
