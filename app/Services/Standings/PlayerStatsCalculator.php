<?php

namespace App\Services\Standings;

use App\Enums\TournamentGameResultEnum;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;

/**
 * Computes per-player stats: TP, DIFF, VP, rounds played, opponent IDs.
 *
 * Pure function of (player + games + scoring config + completed rounds).
 * Single responsibility — does not rank, does not compute SoS.
 */
class PlayerStatsCalculator
{
    /**
     * @param  iterable<TournamentGame>  $allGames  any traversable of games involving the player
     * @param  array{tp: int, diff: int, vp: int}  $byeScoring
     * @param  array<int, TournamentRound>  $completedRounds
     * @param  array<int, int>  $completedRoundNumbers  round_id => round_number lookup
     * @return array{tp: int, diff: int, vp: int, rounds: int, has_bye: bool, wins: int, losses: int, ties: int, opponents: int[]}
     */
    public function compute(
        TournamentPlayer $player,
        iterable $allGames,
        array $byeScoring,
        array $completedRounds,
        array $completedRoundNumbers,
    ): array {
        $playerId = $player->id;
        $tp = 0;
        $diff = 0;
        $vp = 0;
        $rounds = 0;
        $hasBye = false;
        $wins = 0;
        $losses = 0;
        $ties = 0;
        $opponents = [];
        $roundsWithGame = [];
        $playedRoundNumbers = [];

        // Real games walk
        foreach ($allGames as $game) {
            if ($game->player_one_id !== $playerId && $game->player_two_id !== $playerId) {
                continue;
            }
            if ($game->result === TournamentGameResultEnum::Pending) {
                continue;
            }

            $roundsWithGame[$game->tournament_round_id] = true;
            if (isset($completedRoundNumbers[$game->tournament_round_id])) {
                $playedRoundNumbers[$completedRoundNumbers[$game->tournament_round_id]] = true;
            }
            $rounds++;

            if ($game->is_bye) {
                $tp += $byeScoring['tp'];
                $diff += $byeScoring['diff'];
                $vp += $byeScoring['vp'];
                $hasBye = true;
                // A bye scores 3 TP — count it as a win for the W column.
                // NOTE: a player who took a bye in round N then dropped in
                // round N+1 will show wins=1 in standings. This is per
                // current product spec (the bye W still "counts"). If product
                // decides bye-then-drop should clear the W, gate this on
                // `! $isDropped` here.
                $wins++;

                continue;
            }

            $oppId = $game->player_one_id === $playerId ? $game->player_two_id : $game->player_one_id;
            if ($oppId !== null) {
                $opponents[] = $oppId;
            }

            // Forfeit: large fixed swing — winner gets 3 TP / +11 / 11 VP, loser -11 DIFF only.
            if ($game->is_forfeit && $game->forfeit_player_id) {
                if ($game->forfeit_player_id === $playerId) {
                    $diff -= 11;
                    $losses++;
                } else {
                    $tp += 3;
                    $diff += 11;
                    $vp += 11;
                    $wins++;
                }

                continue;
            }

            $isPlayerOne = $game->player_one_id === $playerId;
            $myVp = $isPlayerOne ? ($game->player_one_vp ?? 0) : ($game->player_two_vp ?? 0);
            $oppVp = $isPlayerOne ? ($game->player_two_vp ?? 0) : ($game->player_one_vp ?? 0);

            $vp += $myVp;
            $diff += ($myVp - $oppVp);

            if ($myVp > $oppVp) {
                $tp += 3;
                $wins++;
            } elseif ($myVp === $oppVp) {
                $tp += 1;
                $ties++;
            } else {
                $losses++;
            }
        }

        // Missed-round penalty: one loss (-bye_diff DIFF, 0 TP) for every completed
        // round the player was on the roster + active for but didn't play. Catches
        // un-dropped players returning after sitting out.
        $earliestPlayedRound = empty($playedRoundNumbers) ? null : min(array_keys($playedRoundNumbers));
        foreach ($completedRounds as $round) {
            if (isset($roundsWithGame[$round->id])) {
                continue;
            }
            // Late-add: no earlier game evidence.
            if ($earliestPlayedRound === null || $round->round_number < $earliestPlayedRound) {
                continue;
            }
            // Currently dropped at a point earlier than this round.
            $wasActive = $player->dropped_after_round === null
                || $player->dropped_after_round >= $round->round_number;
            if (! $wasActive) {
                continue;
            }
            $diff -= $byeScoring['diff'];
            $rounds++;
        }

        return [
            'tp' => $tp,
            'diff' => $diff,
            'vp' => $vp,
            'rounds' => $rounds,
            'has_bye' => $hasBye,
            'wins' => $wins,
            'losses' => $losses,
            'ties' => $ties,
            'opponents' => $opponents,
        ];
    }
}
