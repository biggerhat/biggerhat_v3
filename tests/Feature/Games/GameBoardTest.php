<?php

use App\Enums\BaseSizeEnum;
use App\Enums\DeploymentEnum;
use App\Enums\PermissionEnum;
use App\Events\GameBoardAurasChanged;
use App\Events\GameBoardMarkersChanged;
use App\Events\GameBoardMoved;
use App\Events\GameBoardSetupChanged;
use App\Events\GameBoardTerrainChanged;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\Marker;
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
            ->where('game.deployment', fn ($d) => in_array($d, ['standard', 'corner', 'flank', 'wedge'], true) || $d === null)
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

it('hands the board the deployment the game was set up with', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $game->update(['deployment' => DeploymentEnum::Wedge]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('game.deployment', 'wedge'));
});

function terrainPiece(array $overrides = []): array
{
    return array_merge([
        'id' => 'p1', 'name' => 'Tower', 'traits' => ['blocking', 'climbable'],
        'surfaces' => [
            ['id' => 'p1-1', 'top' => 3, 'shape' => ['kind' => 'rect', 'x' => 4, 'y' => 4, 'w' => 6, 'h' => 5]],
            ['id' => 'p1-2', 'top' => 5, 'shape' => ['kind' => 'circle', 'cx' => 6, 'cy' => 6, 'r' => 1.5]],
            ['id' => 'p1-3', 'top' => 1, 'shape' => ['kind' => 'poly', 'points' => [[1, 1], [3, 1], [2, 3]]]],
        ],
    ], $overrides);
}

it('saves the terrain layout for the game, and hands it back with the board', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]])->assertRedirect();

    expect($game->fresh()->board_state['terrain'])->toHaveCount(1);
    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->has('terrain', 1)->where('terrain.0.surfaces.0.top', 3));
});

it('lets either player lay out the table', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());

    $this->actingAs($b)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece(['id' => 'from-b'])]])->assertRedirect();

    expect($game->fresh()->board_state['terrain'][0]['id'])->toBe('from-b');
});

it('keeps positions when the terrain changes, and terrain when models move', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3]]]);
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]]);
    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 9, 'y' => 9]]]);

    $state = $game->fresh()->board_state;
    expect($state['terrain'])->toHaveCount(1)->and($state['positions'][(string) $m1->id]['x'])->toEqual(9);
});

it('can clear the table by saving an empty layout', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]]);

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => []])->assertRedirect();

    expect($game->fresh()->board_state['terrain'])->toBe([]);
});

it('tells the other player when the terrain changes', function () {
    Event::fake([GameBoardTerrainChanged::class]);
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]]);

    Event::assertDispatched(GameBoardTerrainChanged::class, fn ($e) => count($e->terrain) === 1 && $e->broadcastOn()[0]->name === 'presence-game.'.$game->uuid);
});

it('rejects a malformed terrain layout', function (array $piece) {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece($piece)]])->assertSessionHasErrors();

    expect($game->fresh()->board_state)->toBeNull();
})->with([
    'unknown trait' => [['traits' => ['sticky']]],
    'bad id' => [['id' => 'has spaces!']],
    'no name' => [['name' => '']],
    'no surfaces' => [['surfaces' => []]],
    'too many surfaces' => [['surfaces' => array_fill(0, 7, ['id' => 's', 'top' => 1, 'shape' => ['kind' => 'rect', 'x' => 1, 'y' => 1, 'w' => 2, 'h' => 2]])]],
    'height too tall' => [['surfaces' => [['id' => 's', 'top' => 40, 'shape' => ['kind' => 'rect', 'x' => 1, 'y' => 1, 'w' => 2, 'h' => 2]]]]],
    'off the table' => [['surfaces' => [['id' => 's', 'top' => 2, 'shape' => ['kind' => 'rect', 'x' => 50, 'y' => 1, 'w' => 2, 'h' => 2]]]]],
    'rectangle without a size' => [['surfaces' => [['id' => 's', 'top' => 2, 'shape' => ['kind' => 'rect', 'x' => 1, 'y' => 1]]]]],
    'unknown shape' => [['surfaces' => [['id' => 's', 'top' => 2, 'shape' => ['kind' => 'star']]]]],
    'polygon with two points' => [['surfaces' => [['id' => 's', 'top' => 2, 'shape' => ['kind' => 'poly', 'points' => [[1, 1], [2, 2]]]]]]],
]);

it('refuses duplicate piece ids and a layout of more than 40 pieces', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece(), terrainPiece()]])->assertSessionHasErrors();
    $many = array_map(fn ($i) => terrainPiece(['id' => "p$i"]), range(1, 41));
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => $many])->assertSessionHasErrors('terrain');
});

it('keeps terrain edits to people in the game', function () {
    [$game] = boardGame(User::factory()->create(), User::factory()->create());

    $this->actingAs(User::factory()->create())->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]])->assertForbidden();
});

it('hides the terrain endpoint until the flag is on', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => []])->assertNotFound();
});

it('offers the board link on the game screen to players while the flag is on', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->get(route('games.show', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', true));

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($a)->get(route('games.show', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', false));
});

it('does not offer the board link to someone watching the game', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $game->update(['is_observable' => true]);

    $this->actingAs(User::factory()->create())->get(route('games.observe', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', false));
});

function placed(string $id, int $markerId, float $x = 10, float $y = 10, int $owner = 1): array
{
    return ['id' => $id, 'marker_id' => $markerId, 'owner' => $owner, 'x' => $x, 'y' => $y];
}

it('hands the board the marker catalogue with base sizes', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    Marker::factory()->create(['name' => 'Scheme Marker', 'base' => 30]);
    Marker::factory()->create(['name' => 'Blast Marker', 'base' => 50]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->has('markers', 2)
            ->where('markers.0.name', 'Blast Marker')
            ->where('markers.0.base_mm', 50)
            ->where('markers.1.base_mm', 30)
            ->where('placed_markers', []));
});

it('lets a player place a marker for themselves, and hands it back with the board, owner included', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $marker = Marker::factory()->create(['base' => 40]);

    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id, 12.345, 20, 2)]])->assertRedirect();

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->has('placed_markers', 1)->where('placed_markers.0.marker_id', $marker->id)->where('placed_markers.0.x', 12.35)->where('placed_markers.0.owner', 2));
});

it('moves and picks up markers without disturbing the others', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $marker = Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id), placed('m2', $marker->id, 5, 5)]]);
    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('n1', $marker->id, 8, 8, 2)]]);
    // Each works on their own: one moves m1 while the other removes n1 and the first removes m2.
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id, 30, 30)]]);
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['remove' => ['m2']]);

    $saved = collect($game->fresh()->board_state['markers'])->keyBy('id');
    expect($saved->keys()->all())->toEqual(['m1', 'n1'])->and($saved['m1']['x'])->toEqual(30)->and($saved['m1']['owner'])->toBe(1);
});

it('lets only the owner move or pick up a marker, and nobody place one for the other player', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $marker = Marker::factory()->create();
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]]);

    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id, 30, 30, 2)]])->assertForbidden();
    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['remove' => ['m1']])->assertForbidden();
    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('x1', $marker->id, 5, 5, 1)]])->assertForbidden();

    $saved = collect($game->fresh()->board_state['markers']);
    expect($saved)->toHaveCount(1)->and($saved->first()['x'])->toEqual(10)->and($saved->first()['owner'])->toBe(1);
});

it('lets a solo player place and move markers for either side', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create(), solo: true);
    $marker = Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id), placed('n1', $marker->id, 4, 4, 2)]])->assertRedirect();
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['remove' => ['n1']])->assertRedirect();

    expect($game->fresh()->board_state['markers'])->toHaveCount(1);
});

it('keeps positions and terrain when markers change', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $marker = Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3]]]);
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece()]]);
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]]);
    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 9, 'y' => 9]]]);

    $state = $game->fresh()->board_state;
    expect($state['markers'])->toHaveCount(1)->and($state['terrain'])->toHaveCount(1)->and($state['positions'])->toHaveCount(1);
});

it('tells the other player when markers change', function () {
    Event::fake([GameBoardMarkersChanged::class]);
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $marker = Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]]);

    Event::assertDispatched(GameBoardMarkersChanged::class, fn ($e) => count($e->markers) === 1 && $e->broadcastOn()[0]->name === 'presence-game.'.$game->uuid);
});

it('rejects bad marker requests', function (array $body) {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), $body)->assertSessionHasErrors();

    expect($game->fresh()->board_state)->toBeNull();
})->with([
    'nothing to change' => [[]],
    'unknown marker' => [['upsert' => [['id' => 'm1', 'marker_id' => 99999, 'owner' => 1, 'x' => 1, 'y' => 1]]]],
    'off the table' => [['upsert' => [['id' => 'm1', 'marker_id' => 1, 'owner' => 1, 'x' => 40, 'y' => 1]]]],
    'bad id' => [['upsert' => [['id' => 'no spaces!', 'marker_id' => 1, 'owner' => 1, 'x' => 1, 'y' => 1]]]],
    'missing y' => [['upsert' => [['id' => 'm1', 'marker_id' => 1, 'owner' => 1, 'x' => 1]]]],
    'no owner' => [['upsert' => [['id' => 'm1', 'marker_id' => 1, 'x' => 1, 'y' => 1]]]],
    'too many at once' => [['upsert' => array_map(fn ($i) => ['id' => "m$i", 'marker_id' => 1, 'owner' => 1, 'x' => 1, 'y' => 1], range(1, 21))]],
]);

it('lets each player have five of each kind of marker, not six', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $scheme = Marker::factory()->create();
    $blast = Marker::factory()->create();
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => array_map(fn ($i) => placed("m$i", $scheme->id), range(1, 5))])->assertRedirect();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('sixth', $scheme->id)]])->assertSessionHasErrors('upsert');
    // Another kind has its own five, and so does the other player.
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => array_map(fn ($i) => placed("b$i", $blast->id), range(1, 5))])->assertRedirect();
    $this->actingAs($b)->put(route('games.board.markers', $game->uuid), ['upsert' => array_map(fn ($i) => placed("n$i", $scheme->id, 3, 3, 2), range(1, 5))])->assertRedirect();
    // Moving one you already have is not a sixth.
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $scheme->id, 20, 20)]])->assertRedirect();

    expect($game->fresh()->board_state['markers'])->toHaveCount(15);
});

it('keeps marker edits to people in the game, and behind the flag', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $marker = Marker::factory()->create();

    $this->actingAs(User::factory()->create())->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]])->assertForbidden();

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]])->assertNotFound();
});

it('hands the sandbox the marker catalogue', function () {
    Marker::factory()->create(['name' => 'Scheme Marker', 'base' => 30]);

    $this->actingAs(User::factory()->create())->get(route('tools.tabletop'))
        ->assertInertia(fn (Assert $page) => $page->has('markers', 1)->where('markers.0.base_mm', 30));
});

function sharedAura(string $id, string|int $owner, float $range = 6, bool $locked = false): array
{
    return ['id' => $id, 'owner_id' => (string) $owner, 'range' => $range, 'locked' => $locked] + ($locked ? ['x' => 12.3456, 'y' => 20] : []);
}

it('hands the board the auras players have shared', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), $b = User::factory()->create());

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id, 3, true)]])->assertRedirect();

    $this->actingAs($b)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->has('shared_auras', 1)
            ->where('shared_auras.0.owner_id', (string) $m1->id)
            ->where('shared_auras.0.locked', true)
            ->where('shared_auras.0.x', 12.35));
});

it('lets only the model\'s player share, change or withdraw its auras', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id)]]);

    $this->actingAs($b)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('x', $m1->id)]])->assertForbidden();
    $this->actingAs($b)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m2->id, 2)]])->assertForbidden();
    $this->actingAs($b)->put(route('games.board.auras', $game->uuid), ['remove' => ['a1']])->assertForbidden();
    $this->actingAs($b)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('b1', $m2->id, 2)]])->assertRedirect();
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id, 9)]])->assertRedirect();

    $saved = collect($game->fresh()->board_state['auras'])->keyBy('id');
    expect($saved->keys()->all())->toEqual(['a1', 'b1'])->and($saved['a1']['range'])->toEqual(9)->and($saved['a1']['owner_id'])->toBe((string) $m1->id);
});

it('shares auras cast by your markers, and drops them when the marker is picked up', function () {
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $marker = Marker::factory()->create();
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]]);

    $this->actingAs($b)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('x', 'm1')]])->assertForbidden();
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', 'm1')]])->assertRedirect();
    expect($game->fresh()->board_state['auras'])->toHaveCount(1);

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['remove' => ['m1']]);

    expect($game->fresh()->board_state['auras'])->toBe([]);
});

it('lets a model show three auras, not four', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => array_map(fn ($i) => sharedAura("a$i", $m1->id), [1, 2, 3])])->assertRedirect();

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a4', $m1->id)]])->assertSessionHasErrors('upsert');

    expect($game->fresh()->board_state['auras'])->toHaveCount(3);
});

it('lets a solo player share auras on either side', function () {
    [$game, $m1, $m2] = boardGame($a = User::factory()->create(), User::factory()->create(), solo: true);

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id), sharedAura('a2', $m2->id)]])->assertRedirect();

    expect($game->fresh()->board_state['auras'])->toHaveCount(2);
});

it('keeps positions, terrain and markers when auras change', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $marker = Marker::factory()->create();
    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3]]]);
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => [placed('m1', $marker->id)]]);

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id)]]);

    $state = $game->fresh()->board_state;
    expect($state['auras'])->toHaveCount(1)->and($state['markers'])->toHaveCount(1)->and($state['positions'])->toHaveCount(1);
});

it('tells the other player when auras change', function () {
    Event::fake([GameBoardAurasChanged::class]);
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id)]]);

    Event::assertDispatched(GameBoardAurasChanged::class, fn ($e) => count($e->auras) === 1 && $e->broadcastOn()[0]->name === 'presence-game.'.$game->uuid);
});

it('rejects bad aura requests', function (array $body) {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), $body)->assertSessionHasErrors();

    expect($game->fresh()->board_state)->toBeNull();
})->with([
    'nothing to change' => [[]],
    'range too small' => [['upsert' => [['id' => 'a1', 'owner_id' => '1', 'range' => 0, 'locked' => false]]]],
    'range too big' => [['upsert' => [['id' => 'a1', 'owner_id' => '1', 'range' => 40, 'locked' => false]]]],
    'locked without a spot' => [['upsert' => [['id' => 'a1', 'owner_id' => '1', 'range' => 3, 'locked' => true]]]],
    'spot off the table' => [['upsert' => [['id' => 'a1', 'owner_id' => '1', 'range' => 3, 'locked' => true, 'x' => 50, 'y' => 1]]]],
    'bad id' => [['upsert' => [['id' => 'no spaces', 'owner_id' => '1', 'range' => 3, 'locked' => false]]]],
]);

it('keeps aura sharing to people in the game, and behind the flag', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs(User::factory()->create())->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id)]])->assertForbidden();

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [sharedAura('a1', $m1->id)]])->assertNotFound();
});

it('keeps the deployment orientation with the game for both players, and tells the other one', function () {
    Event::fake([GameBoardSetupChanged::class]);
    [$game, $m1] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 3, 'y' => 3]]]);

    $this->actingAs($b)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 2])->assertRedirect();

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('deployment_variant', 2)->has('positions'));
    expect($game->fresh()->board_state['positions'])->toHaveCount(1);
    Event::assertDispatched(GameBoardSetupChanged::class, fn ($e) => $e->deploymentVariant === 2 && $e->broadcastOn()[0]->name === 'presence-game.'.$game->uuid);
});

it('starts the deployment orientation at zero and refuses a bad one', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('deployment_variant', 0));
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 4])->assertSessionHasErrors('deployment_variant');
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), [])->assertSessionHasErrors('deployment_variant');
});

it('keeps the orientation to people in the game, and behind the flag', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs(User::factory()->create())->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 1])->assertForbidden();

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 1])->assertNotFound();
});

it('lets a beta tester into the board while the flag is off, and shows them the link', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $a->givePermissionTo(PermissionEnum::UseTabletopBeta->value);

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertOk();
    $this->actingAs($a)->get(route('games.show', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', true));
    // The other player has no access yet: the board is hidden from them and so is the link.
    $this->actingAs($b)->get(route('games.board', $game->uuid))->assertNotFound();
    $this->actingAs($b)->get(route('games.show', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', false));
    // Saves are held to the same rule.
    $this->actingAs($b)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 1])->assertNotFound();
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 1])->assertRedirect();
});
