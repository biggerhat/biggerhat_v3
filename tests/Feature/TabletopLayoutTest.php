<?php

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TabletopLayout;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(fn () => Feature::for(null)->activate('experiment-tabletop'));

function layoutTerrain(array $overrides = []): array
{
    return [array_merge([
        'id' => 'p1', 'name' => 'Tower', 'kind' => 'tower', 'traits' => ['blocking', 'climbable'],
        'surfaces' => [['id' => 'p1-1', 'top' => 3, 'shape' => ['kind' => 'rect', 'x' => 4, 'y' => 4, 'w' => 6, 'h' => 5]]],
    ], $overrides)];
}

function layoutGame(User $a, array $state = ['terrain' => null], bool $observable = false): Game
{
    $game = Game::factory()->inProgress()->create(['creator_id' => $a->id, 'is_observable' => $observable]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => $a->id, 'slot' => 1]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => User::factory()->create()->id, 'slot' => 2]);
    $state['terrain'] ??= layoutTerrain();
    $game->update(['board_state' => $state]);

    return $game;
}

it('is hidden until the tabletop flag is on', function () {
    Feature::for(null)->deactivate('experiment-tabletop');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('tools.tabletop.layouts.store'), ['name' => 'x', 'terrain' => layoutTerrain()])->assertForbidden();
    expect(TabletopLayout::count())->toBe(0);
});

it('needs a login to save a layout', function () {
    $this->post(route('tools.tabletop.layouts.store'), ['name' => 'x', 'terrain' => layoutTerrain()])->assertRedirect();
    expect(TabletopLayout::count())->toBe(0);
});

it('saves a layout, and lists only your own on the sandbox page', function () {
    $me = User::factory()->create();
    TabletopLayout::factory()->create(['name' => 'Someone elses']);

    $this->actingAs($me)->post(route('tools.tabletop.layouts.store'), [
        'name' => 'City streets', 'description' => 'Tight lanes', 'table_image' => 'cobble', 'terrain' => layoutTerrain(),
    ])->assertRedirect();

    $layout = TabletopLayout::where('user_id', $me->id)->firstOrFail();
    expect($layout->name)->toBe('City streets')->and($layout->is_shared)->toBeFalse()->and($layout->table_image)->toBe('cobble');

    $this->actingAs($me)->get(route('tools.tabletop'))->assertInertia(fn (Assert $page) => $page
        ->has('layouts', 1)->where('layouts.0.name', 'City streets')->where('layouts.0.mine', true)->where('layouts.0.pieces', 1)->where('incoming_layout', null));
});

it('rejects a layout with no name, no pieces, a bad piece or an unknown surface', function (array $change, string $field) {
    $me = User::factory()->create();
    $data = array_merge(['name' => 'Ok', 'terrain' => layoutTerrain()], $change);

    $this->actingAs($me)->post(route('tools.tabletop.layouts.store'), $data)->assertSessionHasErrors($field);
    expect(TabletopLayout::count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'a name that is too long' => [['name' => str_repeat('a', 81)], 'name'],
    'no pieces' => [['terrain' => []], 'terrain'],
    'a bad trait' => [['terrain' => layoutTerrain(['traits' => ['sticky']])], 'terrain.0.traits.0'],
    'an unknown table surface' => [['table_image' => 'lava'], 'table_image'],
]);

it('keeps at most fifty layouts a person', function () {
    $me = User::factory()->create();
    TabletopLayout::factory()->count(TabletopLayout::PER_USER)->for($me)->create();

    $this->actingAs($me)->post(route('tools.tabletop.layouts.store'), ['name' => 'One more', 'terrain' => layoutTerrain()])->assertSessionHasErrors('name');
    expect(TabletopLayout::where('user_id', $me->id)->count())->toBe(TabletopLayout::PER_USER);
});

it('lets the owner rename, share and replace a layout, and nobody else', function () {
    $owner = User::factory()->create();
    $layout = TabletopLayout::factory()->for($owner)->create();

    $this->actingAs($owner)->put(route('tools.tabletop.layouts.update', $layout->uuid), ['name' => 'Renamed', 'visibility' => 'link'])->assertRedirect();
    expect($layout->fresh()->name)->toBe('Renamed')->and($layout->fresh()->is_shared)->toBeTrue();

    $this->actingAs($owner)->put(route('tools.tabletop.layouts.update', $layout->uuid), ['terrain' => layoutTerrain(['name' => 'Keep'])])->assertRedirect();
    expect($layout->fresh()->terrain[0]['name'])->toBe('Keep');

    $this->actingAs($owner)->put(route('tools.tabletop.layouts.update', $layout->uuid), ['name' => ''])->assertSessionHasErrors('name');
    $this->actingAs(User::factory()->create())->put(route('tools.tabletop.layouts.update', $layout->uuid), ['name' => 'Hijack'])->assertForbidden();
    expect($layout->fresh()->name)->toBe('Renamed');
});

it('lets the owner delete a layout, and nobody else', function () {
    $owner = User::factory()->create();
    $layout = TabletopLayout::factory()->shared()->for($owner)->create();

    $this->actingAs(User::factory()->create())->delete(route('tools.tabletop.layouts.destroy', $layout->uuid))->assertForbidden();
    expect(TabletopLayout::count())->toBe(1);
    $this->actingAs($owner)->delete(route('tools.tabletop.layouts.destroy', $layout->uuid))->assertRedirect();
    expect(TabletopLayout::count())->toBe(0);
});

it('opens a shared layout by link and lets anyone signed in save a copy, but keeps a private one hidden', function () {
    $owner = User::factory()->create(['name' => 'Maker']);
    $shared = TabletopLayout::factory()->shared()->for($owner)->create(['name' => 'Shared table']);
    $private = TabletopLayout::factory()->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('tools.tabletop', ['layout' => $shared->uuid]))
        ->assertInertia(fn (Assert $page) => $page->where('incoming_layout.name', 'Shared table')->where('incoming_layout.owner', 'Maker')->where('incoming_layout.mine', false));
    $this->actingAs($other)->get(route('tools.tabletop', ['layout' => $private->uuid]))->assertNotFound();
    $this->actingAs($owner)->get(route('tools.tabletop', ['layout' => $private->uuid]))->assertOk();

    $this->actingAs($other)->post(route('tools.tabletop.layouts.copy', $shared->uuid))->assertRedirect();
    $copy = TabletopLayout::where('user_id', $other->id)->firstOrFail();
    expect($copy->name)->toBe('Shared table')->and($copy->is_shared)->toBeFalse()->and($copy->copied_from)->toBe($shared->uuid)->and($copy->terrain)->toBe($shared->terrain);

    $this->actingAs($other)->post(route('tools.tabletop.layouts.copy', $private->uuid))->assertNotFound();
    expect(TabletopLayout::where('user_id', $other->id)->count())->toBe(1);
});

it('names a copy of your own layout so it can be told apart', function () {
    $me = User::factory()->create();
    $layout = TabletopLayout::factory()->for($me)->create(['name' => 'Mine']);

    $this->actingAs($me)->post(route('tools.tabletop.layouts.copy', $layout->uuid))->assertRedirect();
    expect(TabletopLayout::where('user_id', $me->id)->pluck('name')->all())->toContain('Mine', 'Mine copy');
});

it('saves the table of a game you play in as a layout, with its surface', function () {
    $me = User::factory()->create();
    $game = layoutGame($me, ['terrain' => layoutTerrain(), 'table_image' => 'felt']);

    $this->actingAs($me)->post(route('games.board.layout', $game->uuid), ['name' => 'Round 1 table'])->assertRedirect();
    $layout = TabletopLayout::where('user_id', $me->id)->firstOrFail();
    expect($layout->name)->toBe('Round 1 table')->and($layout->table_image)->toBe('felt')->and($layout->copied_from)->toBe($game->uuid)->and($layout->terrain)->toHaveCount(1);
});

it('lets a stranger save the table of an observable game, but not a private one', function () {
    $stranger = User::factory()->create();
    $open = layoutGame(User::factory()->create(), observable: true);
    $closed = layoutGame(User::factory()->create(), observable: false);

    $this->actingAs($stranger)->post(route('games.board.layout', $open->uuid), ['name' => 'Tournament table'])->assertRedirect();
    expect(TabletopLayout::where('user_id', $stranger->id)->count())->toBe(1);

    $this->actingAs($stranger)->post(route('games.board.layout', $closed->uuid), ['name' => 'Sneaky'])->assertNotFound();
    expect(TabletopLayout::where('user_id', $stranger->id)->count())->toBe(1);
});

it('will not save a game table that has no terrain', function () {
    $me = User::factory()->create();
    $game = layoutGame($me);
    $game->update(['board_state' => ['positions' => []]]);

    $this->actingAs($me)->post(route('games.board.layout', $game->uuid), ['name' => 'Empty'])->assertSessionHasErrors('name');
    expect(TabletopLayout::count())->toBe(0);
});

it('tells the game board page which layouts are yours', function () {
    $me = User::factory()->create();
    $game = layoutGame($me);
    TabletopLayout::factory()->for($me)->create(['name' => 'Kept']);
    TabletopLayout::factory()->create(['name' => 'Not mine']);

    $this->actingAs($me)->get(route('games.board', $game->uuid))->assertInertia(fn (Assert $page) => $page->has('layouts', 1)->where('layouts.0.name', 'Kept'));
});
