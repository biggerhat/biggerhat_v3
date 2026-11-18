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
use App\Models\Token;
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

it('sends each model with what the crew row needs, and a replace on the board keeps its place, health and tokens', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $m1->update(['attached_tokens' => [['id' => 1, 'name' => 'Focus']], 'current_health' => 3, 'max_health' => 8]);
    $game->update(['board_state' => ['positions' => [(string) $m1->id => ['x' => 7, 'y' => 9]]]]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('models.0.member.id', $m1->id)
            ->where('models.0.member.display_name', $m1->display_name)
            ->has('models.0.member.attached_upgrades')
            ->has('models.0.member.defense'));

    $new = Character::factory()->create(['base' => BaseSizeEnum::FortyMM, 'health' => 5]);
    $this->actingAs($a)->post(route('games.play.crew.replace', [$game->uuid, $m1->id]), ['character_id' => $new->id])->assertRedirect();

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('models.0.base_mm', 40)
            ->where('models.0.health', 3)
            ->where('models.0.max_health', 5)
            ->where('models.0.tokens.0.name', 'Focus')
            ->where('positions.'.$m1->id.'.x', 7));
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
    'off the table' => [['surfaces' => [['id' => 's', 'top' => 2, 'shape' => ['kind' => 'rect', 'x' => 100, 'y' => 1, 'w' => 2, 'h' => 2]]]]],
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

it('offers people watching an observable game the read-only board link', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $game->update(['is_observable' => true]);

    $this->actingAs(User::factory()->create())->get(route('games.observe', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', true));

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->actingAs(User::factory()->create())->get(route('games.observe', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('tabletop_enabled', false));
});

it('shows a watcher the board read-only, with nothing theirs to move', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $game->update(['is_observable' => true, 'board_state' => ['positions' => [(string) $m1->id => ['x' => 7, 'y' => 8]], 'terrain' => [terrainPiece()]]]);

    $this->actingAs(User::factory()->create())->get(route('games.board.watch', $game->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Games/Board')
            ->where('read_only', true)
            ->where('players.0.mine', false)
            ->where('players.1.mine', false)
            ->has('models', 2)
            ->where('positions.'.$m1->id.'.x', 7)
            ->has('terrain', 1));
});

it('lets a signed-out visitor watch an observable game, and hides games that are not observable or the flag is off', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->get(route('games.board.watch', $game->uuid))->assertNotFound();

    $game->update(['is_observable' => true]);
    $this->get(route('games.board.watch', $game->uuid))->assertOk()->assertInertia(fn (Assert $page) => $page->where('read_only', true));

    Feature::for(null)->deactivate('experiment-tabletop');
    $this->get(route('games.board.watch', $game->uuid))->assertNotFound();
});

it('marks the players board as not read-only', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('read_only', false)->where('players.0.mine', true));
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

it('hands the board each model\'s Sz for sight lines', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $m1->character->update(['size' => 3, 'speed' => 6]);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('models.0.sz', 3)->where('models.0.sp', 6));
});

it('hands the board each model\'s tracker state and the tokens it can carry', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $focus = Token::factory()->create(['name' => 'Focus']);
    $m1->update(['current_health' => 4, 'max_health' => 9, 'is_activated' => true, 'attached_tokens' => [['id' => $focus->id, 'name' => 'Focus']], 'notes' => 'Hiding']);

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->where('models.0.health', 4)
            ->where('models.0.max_health', 9)
            ->where('models.0.activated', true)
            ->where('models.0.tokens.0.name', 'Focus')
            ->where('models.0.notes', 'Hiding')
            ->where('token_options.0.name', 'Focus'));
});

it('lets a player change their model through the tracker\'s own endpoint', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), $b = User::factory()->create());
    $m1->update(['current_health' => 9, 'max_health' => 9]);

    $this->actingAs($a)->patchJson(route('games.play.crew.update', ['game' => $game->uuid, 'gameCrewMember' => $m1->id]), ['current_health' => 5, 'is_activated' => true, 'notes' => 'Hurt'])->assertOk();
    $this->actingAs($b)->patchJson(route('games.play.crew.update', ['game' => $game->uuid, 'gameCrewMember' => $m1->id]), ['current_health' => 1])->assertForbidden();

    $this->actingAs($a)->get(route('games.board', $game->uuid))
        ->assertInertia(fn (Assert $page) => $page->where('models.0.health', 5)->where('models.0.activated', true)->where('models.0.notes', 'Hurt'));
});

it('keeps the table surface with the game for both players, and tells the other one', function () {
    Event::fake([GameBoardSetupChanged::class]);
    [$game] = boardGame($a = User::factory()->create(), $b = User::factory()->create());

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('table_image', null)->has('table_images', 6)->has('terrain_images', 21));
    $this->actingAs($b)->put(route('games.board.setup', $game->uuid), ['table_image' => 'cobble'])->assertRedirect();

    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->where('table_image', 'cobble')->where('deployment_variant', 0));
    Event::assertDispatched(GameBoardSetupChanged::class, fn ($e) => $e->tableImage === 'cobble');

    // changing the deployment afterwards keeps the surface, and null puts the plain table back
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['deployment_variant' => 3])->assertRedirect();
    expect($game->fresh()->board_state)->toMatchArray(['table_image' => 'cobble', 'deployment_variant' => 3]);
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['table_image' => null])->assertRedirect();
    expect($game->fresh()->board_state)->not->toHaveKey('table_image')->and($game->fresh()->board_state['deployment_variant'])->toBe(3);
});

it('refuses a table surface that is not on offer, and offers uploaded ones', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $upload = \App\Models\TabletopImage::factory()->create(['name' => 'Marble floor']);

    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['table_image' => 'lava'])->assertSessionHasErrors('table_image');
    $this->actingAs($a)->put(route('games.board.setup', $game->uuid), ['table_image' => 'i'.$upload->id])->assertRedirect();
    expect($game->fresh()->board_state['table_image'])->toBe('i'.$upload->id);
    $this->actingAs($a)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->has('table_images', 7));
});

it('keeps each terrain piece\'s kind so it is drawn with its texture, and refuses an unknown kind', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $piece = fn (string $kind) => ['id' => 'p1', 'name' => 'Tower', 'kind' => $kind, 'traits' => ['blocking'], 'surfaces' => [['id' => 'p1-1', 'top' => 3, 'shape' => ['kind' => 'rect', 'x' => 4, 'y' => 4, 'w' => 6, 'h' => 6]]]];

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece('tower')]])->assertRedirect();
    expect($game->fresh()->board_state['terrain'][0]['kind'])->toBe('tower');

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece('spaceship')]])->assertSessionHasErrors('terrain.0.kind');
});

it('accepts every ground terrain kind and the severe trait', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $kinds = ['woods', 'tree', 'brambles', 'river', 'deepwater', 'marsh', 'tallgrass', 'rubble', 'acid', 'fire'];
    $terrain = collect($kinds)->map(fn ($k, $i) => ['id' => "p$i", 'name' => ucfirst($k), 'kind' => $k, 'traits' => ['severe', 'hazardous', 'concealing', 'dense'], 'surfaces' => [['id' => "p$i-1", 'top' => 0, 'shape' => ['kind' => 'circle', 'cx' => 6 + $i * 2, 'cy' => 6, 'r' => 1]]]])->all();

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => $terrain])->assertSessionHasNoErrors()->assertRedirect();
    expect(collect($game->fresh()->board_state['terrain'])->pluck('kind')->all())->toBe($kinds);
});

it('saves turned rectangles and ovals, and refuses a bad ellipse or angle', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $piece = fn (array $shape, string $id = 'p1') => ['id' => $id, 'name' => 'Shape', 'traits' => [], 'surfaces' => [['id' => "$id-1", 'top' => 1, 'shape' => $shape]]];
    $turned = ['kind' => 'rect', 'x' => -2.5, 'y' => 10, 'w' => 12, 'h' => 2, 'angle' => 45];
    $oval = ['kind' => 'ellipse', 'cx' => 12, 'cy' => 12, 'rx' => 4, 'ry' => 2, 'angle' => -20];

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece($turned), $piece($oval, 'p2')]])->assertSessionHasNoErrors()->assertRedirect();
    $saved = $game->fresh()->board_state['terrain'];
    expect($saved[0]['surfaces'][0]['shape']['angle'])->toBe(45)
        ->and($saved[1]['surfaces'][0]['shape'])->toMatchArray(['kind' => 'ellipse', 'rx' => 4, 'ry' => 2, 'angle' => -20]);

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece([...$oval, 'rx' => 0])]])->assertSessionHasErrors('terrain.0.surfaces.0.shape.rx');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece([...$turned, 'angle' => 900])]])->assertSessionHasErrors('terrain.0.surfaces.0.shape.angle');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$piece(['kind' => 'ellipse', 'cx' => 5, 'cy' => 5, 'rx' => 2])]])->assertSessionHasErrors('terrain.0.surfaces.0.shape.ry');
});

it('lets any piece be given a texture kind or none, and keeps an outline\'s background', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $outline = fn (?string $kind) => ['id' => 'o1', 'name' => 'Outline', 'kind' => $kind, 'traits' => [], 'surfaces' => [['id' => 'o1-1', 'top' => 0, 'shape' => ['kind' => 'poly', 'points' => [[1, 1], [6, 1], [6, 5], [3, 7]]]]]];

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$outline('marsh')]])->assertSessionHasNoErrors();
    expect($game->fresh()->board_state['terrain'][0]['kind'])->toBe('marsh');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$outline(null)]])->assertSessionHasNoErrors();
    expect($game->fresh()->board_state['terrain'][0]['kind'] ?? null)->toBeNull();
});

it('saves an archway beneath a level and refuses a silly one', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $bridge = fn ($under) => ['id' => 'b1', 'name' => 'Bridge', 'traits' => ['blocking', 'climbable'], 'surfaces' => [['id' => 'b1-1', 'top' => 3, 'under' => $under, 'shape' => ['kind' => 'rect', 'x' => 6, 'y' => 12, 'w' => 12, 'h' => 3]]]];

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$bridge(2)]])->assertSessionHasNoErrors();
    expect($game->fresh()->board_state['terrain'][0]['surfaces'][0]['under'])->toBe(2);
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$bridge(null)]])->assertSessionHasNoErrors();
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$bridge(0)]])->assertSessionHasErrors('terrain.0.surfaces.0.under');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$bridge(40)]])->assertSessionHasErrors('terrain.0.surfaces.0.under');
});

it('remembers that a model is under a bridge, and forgets it when it moves out', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10, 'y' => 13, 'under' => true]]])->assertRedirect();
    expect($game->fresh()->board_state['positions'][(string) $m1->id])->toEqual(['x' => 10, 'y' => 13, 'under' => true]);

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 20, 'y' => 13]]])->assertRedirect();
    expect($game->fresh()->board_state['positions'][(string) $m1->id])->toEqual(['x' => 20, 'y' => 13]);

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 20, 'y' => 13, 'under' => 'maybe']]])->assertSessionHasErrors('positions.'.$m1->id.'.under');
});

it('saves an enterable building with its doors and windows, and refuses a bad opening', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $house = fn (array $interior) => ['id' => 'h1', 'name' => 'House', 'traits' => ['blocking', 'climbable'], 'interior' => $interior, 'surfaces' => [['id' => 'h1-1', 'top' => 3, 'shape' => ['kind' => 'rect', 'x' => 10, 'y' => 10, 'w' => 6, 'h' => 6]]]];
    $door = ['id' => 'd1', 'kind' => 'door', 'x' => 13, 'y' => 16, 'w' => 2];

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 0, 'openings' => [$door, [...$door, 'id' => 'w1', 'kind' => 'window', 'x' => 16, 'y' => 13, 'w' => 1.5]]])]])->assertSessionHasNoErrors();
    $saved = $game->fresh()->board_state['terrain'][0]['interior'];
    expect($saved['floor'])->toBe(0)->and($saved['openings'])->toHaveCount(2)->and($saved['openings'][1]['kind'])->toBe('window');

    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 1, 'openings' => []])]])->assertSessionHasNoErrors();
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 0, 'openings' => [[...$door, 'kind' => 'portal']]])]])->assertSessionHasErrors('terrain.0.interior.openings.0.kind');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 0, 'openings' => [[...$door, 'w' => 20]]])]])->assertSessionHasErrors('terrain.0.interior.openings.0.w');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 40, 'openings' => []])]])->assertSessionHasErrors('terrain.0.interior.floor');
    $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [$house(['floor' => 0, 'openings' => array_fill(0, 9, $door)])]])->assertSessionHasErrors('terrain.0.interior.openings');
});

it('remembers the terrain traits a model is unaffected by, and refuses an unknown one', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $id = (string) $m1->id;

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10, 'y' => 13, 'ignores' => ['blocking', 'severe']]]])->assertRedirect();
    expect($game->fresh()->board_state['positions'][$id])->toEqual(['x' => 10, 'y' => 13, 'ignores' => ['blocking', 'severe']]);

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10, 'y' => 13]]])->assertRedirect();
    expect($game->fresh()->board_state['positions'][$id])->toEqual(['x' => 10, 'y' => 13]);

    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10, 'y' => 13, 'ignores' => ['flying']]]])->assertSessionHasErrors('positions.'.$id.'.ignores.0');
    $this->actingAs($a)->put(route('games.board.update', $game->uuid), ['positions' => [$m1->id => ['x' => 10, 'y' => 13, 'ignores' => ['severe', 'severe']]]])->assertSessionHasErrors();
});

it('does not count Strategy markers towards the five of a kind', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $turf = Marker::factory()->strategy()->create();
    $scheme = Marker::factory()->create();

    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => array_map(fn ($i) => placed("t$i", $turf->id), range(1, 8))])->assertRedirect();
    expect($game->fresh()->board_state['markers'])->toHaveCount(8);

    // an ordinary marker is still held to five
    $this->actingAs($a)->put(route('games.board.markers', $game->uuid), ['upsert' => array_map(fn ($i) => placed("s$i", $scheme->id), range(1, 6))])->assertSessionHasErrors('upsert');
});

it('saves the terrain traits a shared aura counts as, and refuses an unknown one', function () {
    [$game, $m1] = boardGame($a = User::factory()->create(), User::factory()->create());
    $aura = fn (array $traits) => ['id' => 'a1', 'owner_id' => (string) $m1->id, 'range' => 3, 'locked' => false, 'traits' => $traits];

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [$aura(['concealing', 'severe'])]])->assertRedirect();
    expect($game->fresh()->board_state['auras'][0]['traits'])->toBe(['concealing', 'severe']);

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [$aura([])]])->assertRedirect();
    expect($game->fresh()->board_state['auras'][0])->not->toHaveKey('traits');

    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [$aura(['blocking'])]])->assertSessionHasErrors('upsert.0.traits.0');
    $this->actingAs($a)->put(route('games.board.auras', $game->uuid), ['upsert' => [$aura(['dense', 'dense'])]])->assertSessionHasErrors();
});

it('saves a rules card and a limit on special terrain, and refuses an absurd limit', function () {
    [$game] = boardGame($a = User::factory()->create(), User::factory()->create());
    $put = fn (array $extra) => $this->actingAs($a)->put(route('games.board.terrain', $game->uuid), ['terrain' => [terrainPiece($extra)]]);

    $put(['rules' => 'Models within 2" suffer 1 damage.', 'limit' => 1])->assertRedirect();
    $piece = $game->fresh()->board_state['terrain'][0];
    expect($piece['rules'])->toBe('Models within 2" suffer 1 damage.')->and($piece['limit'])->toBe(1);

    $put(['limit' => 9])->assertSessionHasErrors('terrain.0.limit');
    $put(['rules' => str_repeat('x', 601)])->assertSessionHasErrors('terrain.0.rules');
});
