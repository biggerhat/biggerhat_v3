<?php

namespace App\Services\Standings;

/**
 * Sorts standings rows by the tournament's tiebreaker mode and assigns ranks
 * with joint placing (ties share the same rank). Ringers are kept unranked
 * and pinned to the bottom.
 */
class StandingsRanker
{
    /**
     * @param  array<int, array<string, mixed>>  $standings
     * @param  list<string>  $tiebreakers  ordered keys applied after TP: 'sos', 'diff', 'vp'
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $standings, array $tiebreakers): array
    {
        $rankable = array_values(array_filter($standings, fn ($s) => ! $s['is_ringer']));
        $ringers = array_values(array_filter($standings, fn ($s) => $s['is_ringer']));

        // Every sort key is a higher-is-better total_* column: TP first, then the ruleset's tiebreakers.
        $columns = array_map(fn (string $key) => 'total_'.$key, ['tp', ...$tiebreakers]);

        usort($rankable, function ($a, $b) use ($columns) {
            foreach ($columns as $col) {
                $cmp = $b[$col] <=> $a[$col];
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return 0;
        });

        // Joint placing: identical sort keys share a rank.
        $rank = 1;
        foreach ($rankable as $i => &$entry) {
            if ($i > 0) {
                $prev = $rankable[$i - 1];
                $sameKeys = true;
                foreach ($columns as $col) {
                    if ($entry[$col] !== $prev[$col]) {
                        $sameKeys = false;
                        break;
                    }
                }
                if (! $sameKeys) {
                    $rank = $i + 1;
                }
            }
            $entry['rank'] = $rank;
        }
        unset($entry);

        foreach ($ringers as &$ringer) {
            $ringer['rank'] = null;
        }
        unset($ringer);

        return array_merge($rankable, $ringers);
    }
}
