<?php

namespace App\Tournaments;

use App\Enums\TournamentGameResultEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Services\TournamentCutService;

/**
 * Turns a finished top cut into final placings. Until the final has a winner the standings stay the Swiss ones; after it,
 * the champion is 1st, the finalist 2nd and everyone knocked out in the same round shares the next place (semifinal
 * losers joint 3rd, quarterfinal losers joint 5th, ...). Players who were not in the cut follow, in Swiss order.
 */
class CutPlacings
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  Swiss standings, best first
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $rows, Tournament $tournament): array
    {
        $tournament->loadMissing('rounds.games');
        $cutRounds = $tournament->rounds->where('is_cut', true)->sortBy('round_number')->values();
        if ($cutRounds->isEmpty()) {
            return $rows;
        }

        $final = $cutRounds->last()->games->where('is_bye', false);
        if ($final->count() !== 1 || $final->first()->result === TournamentGameResultEnum::Pending) {
            return $rows; // the cut is still running
        }

        $seedOf = [];
        foreach ($rows as $index => $row) {
            $seedOf[(int) $row['player_id']] = $index;
        }
        $cut = app(TournamentCutService::class);

        $finish = []; // player id => [rank, label]
        foreach ($cutRounds as $round) {
            $games = $round->games->where('is_bye', false);
            foreach ($games as $game) {
                /** @var TournamentGame $game */
                $winner = $cut->winnerId($game, $seedOf);
                $loser = $winner === $game->player_one_id ? $game->player_two_id : $game->player_one_id;
                if ($loser === null) {
                    continue;
                }
                $out = $games->count() * 2;
                $finish[$loser] = $games->count() === 1 ? [2, 'Finalist'] : [$games->count() + 1, 'Top '.$out];
                if ($games->count() === 1 && $winner !== null) {
                    $finish[$winner] = [1, 'Champion'];
                }
            }
        }

        $inCut = [];
        $rest = [];
        foreach ($rows as $row) {
            $id = (int) $row['player_id'];
            if (isset($finish[$id])) {
                $row['rank'] = $finish[$id][0];
                $row['cut_finish'] = $finish[$id][1];
                $inCut[] = $row;
            } else {
                $rest[] = $row;
            }
        }
        usort($inCut, fn (array $a, array $b) => [$a['rank'], $seedOf[(int) $a['player_id']]] <=> [$b['rank'], $seedOf[(int) $b['player_id']]]);

        // Everyone outside the cut keeps their Swiss order (and joint places) but ranks after the cut's players.
        $cutSize = count($inCut);
        $previousSwiss = null;
        $previousNew = null;
        foreach ($rest as $position => &$row) {
            if ($row['rank'] === null) {
                continue; // ringers have no placing
            }
            if ($previousSwiss !== null && $row['rank'] === $previousSwiss) {
                $new = $previousNew;
            } else {
                $new = $cutSize + $position + 1;
            }
            $previousSwiss = $row['rank'];
            $previousNew = $new;
            $row['rank'] = $new;
        }
        unset($row);

        return [...$inCut, ...$rest];
    }
}
