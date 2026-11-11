<?php

namespace App\Tournaments;

use App\Models\Tournament;
use App\Models\User;
use App\Notifications\Tournament\TournamentWaitlistPromoted;

/**
 * Tells waitlisted RSVPs when they get a seat. Call snapshot() before a change that can free a seat, then notify()
 * after it: whoever was waiting before and is not any more has moved under the player limit.
 */
class WaitlistPromotions
{
    /**
     * @return array<int, int> user ids waiting right now
     */
    public static function snapshot(Tournament $tournament): array
    {
        return $tournament->waitlistedUserIds();
    }

    /** @param  array<int, int>  $before  the result of snapshot() taken before the change */
    public static function notify(Tournament $tournament, array $before): void
    {
        if ($before === []) {
            return;
        }

        $promoted = array_diff($before, $tournament->fresh()?->waitlistedUserIds() ?? []);
        if ($promoted === []) {
            return;
        }

        foreach (User::whereIn('id', $promoted)->get() as $user) {
            $user->notify(new TournamentWaitlistPromoted($tournament));
        }
    }
}
