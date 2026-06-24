<?php

namespace App\Http\Controllers\Game\Concerns;

use App\Models\Game;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

trait BroadcastsGameEvents
{
    /**
     * Broadcast a game event to the other participants — but only when there's
     * someone to receive it: a non-solo game, or a solo game being observed.
     * Centralizes the `! is_solo || is_observable` guard that otherwise repeats
     * before every broadcast in the play/setup controllers.
     */
    protected function broadcastToOpponents(Game $game, ShouldBroadcast $event): void
    {
        if (! $game->is_solo || $game->is_observable) {
            broadcast($event)->toOthers();
        }
    }
}
