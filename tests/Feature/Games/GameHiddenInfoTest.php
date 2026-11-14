<?php

use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Strategy::factory()->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $this->schemes = Scheme::factory()->count(3)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    $this->a = User::factory()->create();
    $this->b = User::factory()->create();
    $this->game = Game::factory()->create([
        'creator_id' => $this->a->id,
        'status' => GameStatusEnum::SchemeSelect,
        'is_solo' => false,
        'is_observable' => true,
        'scheme_pool' => $this->schemes->pluck('id')->all(),
    ]);
    $this->p1 = GamePlayer::factory()->create([
        'game_id' => $this->game->id, 'user_id' => $this->a->id, 'slot' => 1, 'faction' => 'guild', 'master_name' => 'Lady Justice',
        'current_scheme_id' => $this->schemes[0]->id, 'scheme_notes' => ['note' => 'SECRET-NOTE-ONE', 'selected_model' => 'SECRET-MODEL'],
    ]);
    $this->p2 = GamePlayer::factory()->create(['game_id' => $this->game->id, 'user_id' => $this->b->id, 'slot' => 2, 'faction' => 'bayou', 'master_name' => 'Som\'er']);
});

it('does not show an opponent the scheme (or its notes) their opponent picked during scheme select', function () {
    $json = json_encode(
        $this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->assertOk()->viewData('page')['props'],
    );

    expect($json)->not->toContain('SECRET-NOTE-ONE')->not->toContain('SECRET-MODEL');
    $schemeName = $this->schemes[0]->name;
    $this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.players', fn ($players) => collect($players)->firstWhere('slot', 1)['current_scheme_id'] === null));
});

it('keeps both players\' scheme picks out of the public observer view during scheme select', function () {
    $this->p2->update(['current_scheme_id' => $this->schemes[1]->id]);

    $json = json_encode($this->get(route('games.observe', $this->game->uuid))->assertOk()->viewData('page')['props']);

    expect($json)->not->toContain('SECRET-NOTE-ONE');
    $this->get(route('games.observe', $this->game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.players', fn ($players) => collect($players)->every(fn ($p) => ($p['current_scheme_id'] ?? null) === null)));
});

it('still tells the opponent that a scheme has been locked in, without saying which', function () {
    $this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.players', fn ($players) => collect($players)->firstWhere('slot', 1)['scheme_ready'] === true
            && collect($players)->firstWhere('slot', 2)['scheme_ready'] === false));
});

it('lets a player see their own scheme pick and notes', function () {
    $this->actingAs($this->a)->get(route('games.show', $this->game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.players', fn ($players) => collect($players)->firstWhere('slot', 1)['current_scheme_id'] === $this->schemes[0]->id
            && collect($players)->firstWhere('slot', 1)['scheme_notes']['note'] === 'SECRET-NOTE-ONE'));
});

it('keeps a solo player\'s own opponent scheme visible, since they pick both', function () {
    $this->game->update(['is_solo' => true]);
    $this->p2->update(['user_id' => null, 'current_scheme_id' => $this->schemes[1]->id]);

    $this->actingAs($this->a)->get(route('games.show', $this->game->uuid))->assertInertia(fn (Assert $page) => $page
        ->where('game.players', fn ($players) => collect($players)->firstWhere('slot', 2)['current_scheme_id'] === $this->schemes[1]->id));
});

// ─── In play ───

use App\Models\GameTurn;

function inPlayWithHiddenSchemes(object $t): void
{
    $t->game->update(['status' => GameStatusEnum::InProgress, 'current_turn' => 3]);
    // Player 1 (the one being hidden): turn 1 scored scheme 0, turn 2 is holding scheme 1 and has picked scheme 2 next.
    $t->p1->update(['current_scheme_id' => $t->schemes[1]->id, 'scheme_pool' => [$t->schemes[2]->id]]);
    GameTurn::create(['game_id' => $t->game->id, 'game_player_id' => $t->p1->id, 'turn_number' => 1, 'scheme_id' => $t->schemes[0]->id,
        'scheme_action' => 'scored', 'next_scheme_id' => $t->schemes[1]->id, 'scheme_points' => 2, 'scheme_notes' => ['note' => 'REVEALED-NOTE']]);
    GameTurn::create(['game_id' => $t->game->id, 'game_player_id' => $t->p1->id, 'turn_number' => 2, 'scheme_id' => $t->schemes[1]->id,
        'scheme_action' => 'held', 'next_scheme_id' => $t->schemes[2]->id, 'scheme_points' => 0, 'scheme_notes' => ['note' => 'HIDDEN-NOTE']]);
}

it('keeps the opponent\'s current scheme, notes, pool and unrevealed turns out of the page data during play', function () {
    inPlayWithHiddenSchemes($this);

    $props = $this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->assertOk()->viewData('page')['props'];
    $p1 = collect($props['game']['players'])->firstWhere('slot', 1);

    expect($p1['current_scheme_id'])->toBeNull()
        ->and($p1['scheme_notes'])->toBeNull()
        ->and($p1['scheme_pool'])->toBeNull()
        ->and($p1['scheme_ready'])->toBeTrue();

    $turns = collect($p1['turns'])->keyBy('turn_number');
    expect($turns[2]['scheme_id'])->toBeNull()->and($turns[2]['scheme_notes'])->toBeNull()
        ->and($turns[1]['next_scheme_id'])->toBeNull()->and($turns[2]['next_scheme_id'])->toBeNull();
    // Scores stay public, and a scheme that has been scored is revealed.
    expect($turns[1]['scheme_points'])->toBe(2)->and($turns[1]['scheme_id'])->toBe($this->schemes[0]->id);

    expect(json_encode($props))->not->toContain('HIDDEN-NOTE');
    // The hidden current scheme is not smuggled in through the schemes list either.
    expect(collect($props['current_schemes'])->pluck('id')->all())->not->toContain($this->schemes[1]->id);
});

it('still gives the opponent-intel panel its revealed scheme during play', function () {
    inPlayWithHiddenSchemes($this);

    $intel = $this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->viewData('page')['props']['opponent_scheme_intel'];

    expect($intel['last_revealed']['scheme_id'])->toBe($this->schemes[0]->id)
        ->and($intel['last_revealed']['scheme_action'])->toBe('scored');
});

it('shows a player everything of their own during play', function () {
    inPlayWithHiddenSchemes($this);

    $props = $this->actingAs($this->a)->get(route('games.show', $this->game->uuid))->viewData('page')['props'];
    $p1 = collect($props['game']['players'])->firstWhere('slot', 1);
    $turns = collect($p1['turns'])->keyBy('turn_number');

    expect($p1['current_scheme_id'])->toBe($this->schemes[1]->id)
        ->and($turns[2]['scheme_id'])->toBe($this->schemes[1]->id)
        ->and($turns[2]['next_scheme_id'])->toBe($this->schemes[2]->id)
        ->and(collect($props['current_schemes'])->pluck('id')->all())->toContain($this->schemes[1]->id);
});

it('shows the whole game back once it is over', function () {
    inPlayWithHiddenSchemes($this);
    $this->game->update(['status' => GameStatusEnum::Completed]);

    $p1 = collect($this->actingAs($this->b)->get(route('games.show', $this->game->uuid))->viewData('page')['props']['game']['players'])->firstWhere('slot', 1);

    expect($p1['current_scheme_id'])->toBe($this->schemes[1]->id);
});
