<?php

namespace App\Services\Game;

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Game;

/**
 * The clock of the tournament round a tracker game belongs to (Gaining Grounds One, Round Time Limit): when the round ends, and when "last
 * turn" is called (15 minutes before, or what the event's format says). Public to everyone at the table. Null for a game that is not part
 * of a running round.
 */
class RoundClock
{
    /**
     * @return array{now: string, started_at: string, ends_at: string, last_turn_at: string, limit: int}|null
     */
    public function for(Game $game): ?array
    {
        $round = $game->tournamentGame?->round;
        if ($round === null || $round->status !== TournamentRoundStatusEnum::InProgress || $round->started_at === null) {
            return null;
        }
        $tournament = $round->tournament;
        $limit = (int) $tournament->round_time_limit;
        if ($limit <= 0) {
            return null;
        }
        $lastTurn = (int) ($tournament->rulesetDefinition()->timeRules['last_turn_minutes'] ?? 15);
        $ends = $round->started_at->copy()->addMinutes($limit);

        return [
            // the server's time, so a browser with a wrong clock still counts down correctly
            'now' => now()->toIso8601String(),
            'started_at' => $round->started_at->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'last_turn_at' => $ends->copy()->subMinutes($lastTurn)->toIso8601String(),
            'limit' => $limit,
        ];
    }
}
