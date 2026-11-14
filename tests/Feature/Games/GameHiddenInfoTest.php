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
