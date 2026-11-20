<?php

use App\Enums\GamePlayModeEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\TabletopLayout;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

beforeEach(function () {
    Strategy::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    Scheme::factory()->count(5)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
});

/** @return array<string, mixed> */
function newGameInput(array $extra = []): array
{
    return ['season' => PoolSeasonEnum::GainingGrounds0->value, 'encounter_size' => 50, ...$extra];
}

it('leaves the play mode empty when the form does not send one', function () {
    $this->actingAs(User::factory()->create())->post(route('games.store'), newGameInput())->assertRedirect();

    expect(Game::latest('id')->first())->play_mode->toBeNull()->tabletop_layout_id->toBeNull();
});

it('records in person, online and other without needing the tabletop', function (string $mode) {
    $this->actingAs(User::factory()->create())->post(route('games.store'), newGameInput(['play_mode' => $mode]))->assertRedirect();

    expect(Game::latest('id')->first()->play_mode)->toBe(GamePlayModeEnum::from($mode));
})->with(['in_person', 'online', 'other']);

it('rejects an unknown mode', function () {
    $this->actingAs(User::factory()->create())->post(route('games.store'), newGameInput(['play_mode' => 'carrier_pigeon']))->assertSessionHasErrors('play_mode');
});

it('keeps the tabletop for people who have it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('games.store'), newGameInput(['play_mode' => 'tabletop']))->assertSessionHasErrors('play_mode');
    expect(Game::count())->toBe(0);

    Feature::for(null)->activate('experiment-tabletop');
    $this->actingAs($user)->post(route('games.store'), newGameInput(['play_mode' => 'tabletop']))->assertRedirect();
    expect(Game::latest('id')->first()->play_mode)->toBe(GamePlayModeEnum::Tabletop);
});

it('starts a tabletop game on a copy of the chosen saved table', function () {
    Feature::for(null)->activate('experiment-tabletop');
    $user = User::factory()->create();
    $layout = TabletopLayout::factory()->create(['user_id' => $user->id, 'table_image' => null]);

    $this->actingAs($user)->post(route('games.store'), newGameInput(['play_mode' => 'tabletop', 'tabletop_layout' => $layout->uuid]))->assertRedirect();

    $game = Game::latest('id')->first();
    expect($game->tabletop_layout_id)->toBe($layout->id)->and($game->board_state['terrain'])->toBe($layout->terrain);

    // editing or deleting the layout later does not touch the game
    $layout->update(['terrain' => []]);
    expect($game->fresh()->board_state['terrain'])->not->toBe([]);
    $layout->delete();
    expect($game->fresh()->board_state['terrain'])->not->toBe([]);
});

it('does not let a game use someone else\'s table, or a table without the tabletop', function () {
    Feature::for(null)->activate('experiment-tabletop');
    $user = User::factory()->create();
    $theirs = TabletopLayout::factory()->create();
    $mine = TabletopLayout::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('games.store'), newGameInput(['play_mode' => 'tabletop', 'tabletop_layout' => $theirs->uuid]))->assertSessionHasErrors('tabletop_layout');
    $this->actingAs($user)->post(route('games.store'), newGameInput(['play_mode' => 'in_person', 'tabletop_layout' => $mine->uuid]))->assertSessionHasErrors('tabletop_layout');
    expect(Game::count())->toBe(0);
});

it('an empty table is allowed on the tabletop', function () {
    Feature::for(null)->activate('experiment-tabletop');

    $this->actingAs(User::factory()->create())->post(route('games.store'), newGameInput(['play_mode' => 'tabletop']))->assertRedirect();

    expect(Game::latest('id')->first())->tabletop_layout_id->toBeNull()->and(Game::latest('id')->first()->board_state)->toBeNull();
});

it('offers the create form its modes, and the tabletop and the user\'s tables only with access', function () {
    $user = User::factory()->create();
    TabletopLayout::factory()->create(['user_id' => $user->id, 'name' => 'Mine']);

    $this->actingAs($user)->get(route('games.create'))->assertInertia(fn (Assert $page) => $page
        ->component('Games/Create')
        ->has('play_modes', 3)
        ->where('play_modes.0.value', 'in_person')
        ->where('tabletop_layouts', []));

    Feature::for(null)->activate('experiment-tabletop');
    $this->actingAs($user)->get(route('games.create'))->assertInertia(fn (Assert $page) => $page
        ->has('play_modes', 4)
        ->has('tabletop_layouts', 1)
        ->where('tabletop_layouts.0.name', 'Mine'));
});
