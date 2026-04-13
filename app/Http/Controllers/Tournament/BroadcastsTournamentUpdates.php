<?php

namespace App\Http\Controllers\Tournament;

use App\Events\TournamentUpdated;
use App\Models\Tournament;

trait BroadcastsTournamentUpdates
{
    /**
     * Notify other listeners (spectators on the public View page,
     * collaborating TOs in another tab) that the tournament changed.
     *
     * The action label is informational — we send it so clients can choose
     * which props to refresh in the future, without changing the protocol.
     */
    protected function broadcastUpdate(Tournament $tournament, string $action = 'updated'): void
    {
        broadcast(new TournamentUpdated($tournament, $action))->toOthers();
    }
}
