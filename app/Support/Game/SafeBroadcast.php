<?php

namespace App\Support\Game;

use Closure;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

/**
 * Sends a live update after the change it describes is already saved. If the websocket server cannot be reached (it is not running on a
 * developer machine, or is briefly down) the player's action must still succeed: the other screen catches up on its next reload.
 */
final class SafeBroadcast
{
    public static function send(Closure $broadcast): void
    {
        try {
            $broadcast();
        } catch (BroadcastException $e) {
            Log::warning('Live update not sent: '.$e->getMessage());
        }
    }
}
