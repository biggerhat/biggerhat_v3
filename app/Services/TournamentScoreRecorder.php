<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Models\TournamentGame;

/**
 * Makes a score official. The one place that turns ruleset score components into a game's
 * VP totals, breakdown and result, shared by organizer score entry and by confirming a
 * player-submitted report (which is cleared here, so any official score supersedes it).
 */
class TournamentScoreRecorder
{
    /**
     * @param  array<string, int>  $one  player one's score components, keyed by the ruleset's score-field keys
     * @param  array<string, int>  $two  player two's score components
     * @param  array<string, mixed>  $attributes  extra columns to store with the score (leader, faction, crew build…)
     */
    public function record(TournamentGame $game, array $one, array $two, bool $agreed = false, array $attributes = []): void
    {
        $game->update([
            ...$attributes,
            'player_one_vp' => array_sum($one),
            'player_two_vp' => array_sum($two),
            'score_breakdown' => ['player_one' => $one, 'player_two' => $two],
            // Scored exactly like a played game; "agreed" just records that it ended by agreement.
            'result' => $agreed ? TournamentGameResultEnum::Agreed : TournamentGameResultEnum::Completed,
            ...$this->clearedReport(),
        ]);
    }

    /**
     * Columns that drop any pending / disputed player report.
     *
     * @return array<string, null>
     */
    public function clearedReport(): array
    {
        return [
            'report_status' => null,
            'reported_by_player_id' => null,
            'reported_at' => null,
            'reported_score' => null,
            'report_note' => null,
        ];
    }
}
