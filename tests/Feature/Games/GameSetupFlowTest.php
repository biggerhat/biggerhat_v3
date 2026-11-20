<?php

use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Support\Game\GameSetupFlow;

it('advances a stored game once: a second request from the same status changes nothing, and play starts on turn 1', function () {
    $game = Game::factory()->create(['status' => GameStatusEnum::SchemeSelect, 'current_turn' => 0]);

    expect(GameSetupFlow::advance($game, GameStatusEnum::SchemeSelect))->toBeTrue();
    expect($game->fresh())->status->toBe(GameStatusEnum::InProgress)->current_turn->toBe(1);

    // a request that still believes the game is at the scheme step, after play has started
    $game->update(['current_turn' => 3]);
    $stale = Game::find($game->id);
    expect(GameSetupFlow::advance($stale, GameStatusEnum::SchemeSelect))->toBeFalse();
    expect($stale->fresh()->current_turn)->toBe(3);
});

it('writes the extra attributes in the same update', function () {
    $game = Game::factory()->create(['status' => GameStatusEnum::Setup, 'started_at' => null]);

    GameSetupFlow::advance($game, GameStatusEnum::Setup, ['started_at' => now()]);

    expect($game->fresh())->status->toBe(GameStatusEnum::FactionSelect)->started_at->not->toBeNull();
});
