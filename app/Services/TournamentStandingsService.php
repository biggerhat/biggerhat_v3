<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;

class TournamentStandingsService
{
    /**
     * Compute standings for all eligible players in a tournament.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compute(Tournament $tournament): array
    {
        $tournament->loadMissing(['players', 'rounds.games']);

        /** @var \Illuminate\Database\Eloquent\Collection<int, TournamentPlayer> $players */
        $players = $tournament->players->where('is_disqualified', false);

        /** @var \Illuminate\Support\Collection<int, TournamentGame> $allGames */
        $allGames = $tournament->rounds->flatMap(fn ($round) => $round->games);

        $standings = [];
        foreach ($players as $player) {
            $stats = $this->computePlayerStats($player->id, $allGames);
            $standings[] = [
                'player_id' => $player->id,
                'display_name' => $player->display_name,
                'user_id' => $player->user_id,
                'faction' => $player->getRawOriginal('faction'),
                'is_ringer' => $player->is_ringer,
                'is_dropped' => $player->dropped_after_round !== null,
                'total_tp' => $stats['tp'],
                'total_diff' => $stats['diff'],
                'total_vp' => $stats['vp'],
                'rounds_played' => $stats['rounds'],
                'has_bye' => $stats['has_bye'],
            ];
        }

        // Separate ringers and rankable players
        $rankable = array_values(array_filter($standings, fn ($s) => ! $s['is_ringer']));
        $ringers = array_values(array_filter($standings, fn ($s) => $s['is_ringer']));

        // Sort: TP desc, DIFF desc, VP desc
        usort($rankable, function ($a, $b) {
            return ($b['total_tp'] <=> $a['total_tp'])
                ?: ($b['total_diff'] <=> $a['total_diff'])
                ?: ($b['total_vp'] <=> $a['total_vp']);
        });

        // Assign ranks with joint placing
        $rank = 1;
        foreach ($rankable as $i => &$entry) {
            if ($i > 0) {
                $prev = $rankable[$i - 1];
                if ($entry['total_tp'] !== $prev['total_tp']
                    || $entry['total_diff'] !== $prev['total_diff']
                    || $entry['total_vp'] !== $prev['total_vp']) {
                    $rank = $i + 1;
                }
            }
            $entry['rank'] = $rank;
        }
        unset($entry);

        // Ringers get no rank
        foreach ($ringers as &$ringer) {
            $ringer['rank'] = null;
        }
        unset($ringer);

        return array_merge($rankable, $ringers);
    }

    /**
     * Compute TP, DIFF, VP for a single player across all their games.
     *
     * @param  \Illuminate\Support\Collection<int, TournamentGame>  $allGames
     * @return array{tp: int, diff: int, vp: int, rounds: int, has_bye: bool}
     */
    private function computePlayerStats(int $playerId, $allGames): array
    {
        $tp = 0;
        $diff = 0;
        $vp = 0;
        $rounds = 0;
        $hasBye = false;

        foreach ($allGames as $game) {
            if ($game->player_one_id !== $playerId && $game->player_two_id !== $playerId) {
                continue;
            }
            if ($game->result === TournamentGameResultEnum::Pending) {
                continue;
            }

            $rounds++;

            if ($game->is_bye) {
                $tp += 3;
                $diff += 4;
                $vp += 6;
                $hasBye = true;

                continue;
            }

            if ($game->is_forfeit && $game->forfeit_player_id) {
                if ($game->forfeit_player_id === $playerId) {
                    $diff -= 11;
                } else {
                    $tp += 3;
                    $diff += 11;
                    $vp += 11;
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
            } elseif ($myVp === $oppVp) {
                $tp += 1;
            }
        }

        return ['tp' => $tp, 'diff' => $diff, 'vp' => $vp, 'rounds' => $rounds, 'has_bye' => $hasBye];
    }
}
