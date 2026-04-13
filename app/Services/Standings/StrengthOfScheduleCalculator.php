<?php

namespace App\Services\Standings;

/**
 * Sum-of-opponents'-TP (Buchholz / Solkoff) calculator.
 *
 * Runs as a second pass — needs everyone's TP already computed in pass 1.
 * Byes contribute 0 (no opponent).
 */
class StrengthOfScheduleCalculator
{
    /**
     * @param  array<int, array{player_id: int, total_tp: int}>  $standings
     * @param  array<int, int[]>  $opponentsByPlayer  player_id => [opponent_ids]
     * @return array<int, array<string, mixed>> same standings rows + total_sos
     */
    public function annotate(array $standings, array $opponentsByPlayer): array
    {
        $tpById = [];
        foreach ($standings as $row) {
            $tpById[$row['player_id']] = $row['total_tp'];
        }

        return array_map(function (array $entry) use ($tpById, $opponentsByPlayer): array {
            $sos = 0;
            foreach ($opponentsByPlayer[$entry['player_id']] ?? [] as $oppId) {
                $sos += (int) ($tpById[$oppId] ?? 0);
            }
            $entry['total_sos'] = $sos;

            return $entry;
        }, $standings);
    }
}
