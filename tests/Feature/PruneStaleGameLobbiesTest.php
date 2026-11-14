<?php

use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lobby(array $attrs = [], bool $withOpponent = false): Game
{
    $game = Game::factory()->create(['status' => GameStatusEnum::Setup, 'is_solo' => false, ...$attrs]);
    GamePlayer::factory()->for($game, 'game')->create(['user_id' => User::factory(), 'slot' => 1]);
    if ($withOpponent) {
        GamePlayer::factory()->for($game, 'game')->create(['user_id' => User::factory(), 'slot' => 2]);
    }

    return $game;
}

it('removes lobbies nobody joined within the window, and only those', function () {
    $stale = lobby(['created_at' => now()->subDays(10)]);
    $fresh = lobby(['created_at' => now()->subDays(2)]);
    $joined = lobby(['created_at' => now()->subDays(10), 'status' => GameStatusEnum::FactionSelect], withOpponent: true);
    $solo = lobby(['created_at' => now()->subDays(10), 'is_solo' => true]);

    $this->artisan('games:prune-stale-lobbies')->assertSuccessful();

    expect(Game::find($stale->id))->toBeNull()
        ->and(Game::find($fresh->id))->not->toBeNull()
        ->and(Game::find($joined->id))->not->toBeNull()
        ->and(Game::find($solo->id))->not->toBeNull();
});

it('honours a custom number of days', function () {
    $game = lobby(['created_at' => now()->subDays(3)]);

    $this->artisan('games:prune-stale-lobbies', ['--days' => 2])->assertSuccessful();

    expect(Game::find($game->id))->toBeNull();
});
