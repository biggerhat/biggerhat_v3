<?php

namespace App\Services\Standings;

use App\Enums\TournamentGameResultEnum;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Tournaments\ScoringEngine;

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
     * @param  array<int, TournamentRound>  $completedRounds
     * @param  array<int, int>  $completedRoundNumbers  round_id => round_number lookup
     * @return array{tp: int, diff: int, vp: int, rounds: int, has_bye: bool, wins: int, losses: int, ties: int, opponents: int[]}
     */
    public function compute(
        TournamentPlayer $player,
        iterable $allGames,
        ScoringEngine $scoring,
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
                $bye = $scoring->bye();
                $tp += $bye['tp'];
                $diff += $bye['diff'];
                $vp += $bye['vp'];
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

            // Forfeit: fixed swing defined by the ruleset's scoring config.
            if ($game->is_forfeit && $game->forfeit_player_id) {
                $forfeiter = $game->forfeit_player_id === $playerId;
                $forfeit = $forfeiter ? $scoring->forfeitLoss() : $scoring->forfeitWin();
                $tp += $forfeit['tp'];
                $diff += $forfeit['diff'];
                $vp += $forfeit['vp'];
                if ($forfeiter) {
                    $losses++;
                } else {
                    $wins++;
                }

                continue;
            }

            $isPlayerOne = $game->player_one_id === $playerId;
            $myVp = $isPlayerOne ? ($game->player_one_vp ?? 0) : ($game->player_two_vp ?? 0);
            $oppVp = $isPlayerOne ? ($game->player_two_vp ?? 0) : ($game->player_one_vp ?? 0);

            $result = $scoring->game($myVp, $oppVp);
            $tp += $result['tp'];
            $diff += $result['diff'];
            $vp += $result['vp'];

            match ($result['outcome']) {
                'win' => $wins++,
                'tie' => $ties++,
                'loss' => $losses++,
            };
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
            $diff -= $scoring->missedRoundDiff();
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
