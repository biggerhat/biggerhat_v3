<?php

namespace App\Observers;

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Game;

/**
 * Campaign-context hooks on the `Game` model. Currently fires one rule:
 * when a campaign-format game transitions to Completed, increment the
 * winning crew's `total_wins` (used by the Competitive Campaign optional
 * rule and surfaced in the campaign hub). Ties don't increment either side.
 *
 * Wired via `ObservedBy(GameObserver::class)` attribute on the Game model
 * so non-campaign games are paid only the cost of an enum compare on update.
 */
class GameObserver
{
    public function updated(Game $game): void
    {
        // Only campaign games matter here.
        if ($game->format !== GameFormatEnum::Campaign) {
            return;
        }

        if (! $game->wasChanged('status')) {
            return;
        }

        if ($game->status !== GameStatusEnum::Completed) {
            return;
        }

        $wrap = CampaignGame::query()->where('base_game_id', $game->id)->first();
        if (! $wrap) {
            return;
        }

        // VP totals live on `game_players.total_points`, not on the games row.
        // Game::playerOne()/playerTwo() are plain helper methods (not Eloquent
        // relations), so call them and read total_points off the GamePlayer.
        // PHPStan reads the playerOne()/playerTwo() return type as non-null
        // via the IDE-helper stub, so we sidestep `?->` and explicitly null-
        // check.
        $p1 = $game->playerOne();
        $p2 = $game->playerTwo();
        $wrap->fill([
            'vp_a' => $p1 ? (int) $p1->total_points : 0,
            'vp_b' => $p2 ? (int) $p2->total_points : 0,
            'status' => 'closed',
        ]);

        // Resolve winning crew via winner_id (the user_id), then look up which
        // crew that user owns in the same campaign.
        if ($game->winner_id && ! $game->is_tie) {
            $winnerCrewId = null;
            if ($wrap->crewA && $wrap->crewA->user_id === $game->winner_id) {
                $winnerCrewId = $wrap->crew_a_id;
            } elseif ($wrap->crewB && $wrap->crewB->user_id === $game->winner_id) {
                $winnerCrewId = $wrap->crew_b_id;
            }

            if ($winnerCrewId) {
                $wrap->winner_crew_id = $winnerCrewId;
                // Track wins regardless of competitive flag — the column exists
                // for everyone and the campaign hub surfaces it as a stat. The
                // "competitive" toggle just decides whether the campaign ends
                // with a declared winner; the per-game wins are useful data
                // either way.
                CampaignCrew::query()
                    ->whereKey($winnerCrewId)
                    ->increment('total_wins');
            }
        }

        $wrap->save();
    }
}
