<?php

use App\Enums\PoolSeasonEnum;
use App\Models\Game;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\User;

beforeEach(function () {
    Strategy::factory()->count(4)->create(['season' => PoolSeasonEnum::GainingGrounds0]);
    Scheme::factory()->count(6)->create(['season' => PoolSeasonEnum::GainingGrounds0]);

    $this->user = User::factory()->create();
    $this->game = Game::factory()->create([
        'creator_id' => $this->user->id,
        'season' => PoolSeasonEnum::GainingGrounds0,
        'status' => \App\Enums\GameStatusEnum::Setup,
    ]);
});

it('regenerates strategy/deployment/scheme_pool for the creator', function () {
    $before = [
        'strategy_id' => $this->game->strategy_id,
        'deployment' => $this->game->deployment,
        'scheme_pool' => $this->game->scheme_pool,
    ];

    $this->actingAs($this->user)
        ->postJson(route('games.scenario.regenerate', $this->game->uuid))
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->game->refresh();

    expect($this->game->strategy_id)->not->toBeNull();
    expect($this->game->deployment)->not->toBeNull();
    expect($this->game->scheme_pool)->toBeArray();
    expect(count($this->game->scheme_pool))->toBe(3);

    // Best-effort drift check — the new pool should differ in at least one
    // dimension from the (likely empty) starting state.
    expect([
        $this->game->strategy_id,
        $this->game->deployment,
        $this->game->scheme_pool,
    ])->not->toBe([$before['strategy_id'], $before['deployment'], $before['scheme_pool']]);
});

it('blocks non-creators from regenerating', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->postJson(route('games.scenario.regenerate', $this->game->uuid))
        ->assertForbidden();
});

it('blocks regenerate once the game is in progress', function () {
    $this->game->update(['status' => \App\Enums\GameStatusEnum::InProgress]);

    $this->actingAs($this->user)
        ->postJson(route('games.scenario.regenerate', $this->game->uuid))
        ->assertForbidden();
});
