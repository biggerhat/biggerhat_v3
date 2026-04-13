<?php

namespace App\Services\Standings;

use App\Enums\TournamentTiebreakerEnum;

/**
 * Sorts standings rows by the tournament's tiebreaker mode and assigns ranks
 * with joint placing (ties share the same rank). Ringers are kept unranked
 * and pinned to the bottom.
 */
class StandingsRanker
{
    /**
     * @param  array<int, array<string, mixed>>  $standings
     * @return array<int, array<string, mixed>>
     */
    public function rank(array $standings, TournamentTiebreakerEnum $mode): array
    {
        $rankable = array_values(array_filter($standings, fn ($s) => ! $s['is_ringer']));
        $ringers = array_values(array_filter($standings, fn ($s) => $s['is_ringer']));

        $useSos = $mode === TournamentTiebreakerEnum::Sos;
        usort($rankable, function ($a, $b) use ($useSos) {
            $cmp = $b['total_tp'] <=> $a['total_tp'];
            if ($cmp !== 0) {
                return $cmp;
            }
            if ($useSos) {
                $sosCmp = $b['total_sos'] <=> $a['total_sos'];
                if ($sosCmp !== 0) {
                    return $sosCmp;
                }
            }

            return ($b['total_diff'] <=> $a['total_diff'])
                ?: ($b['total_vp'] <=> $a['total_vp']);
        });

        // Joint placing: identical sort keys share a rank.
        $rank = 1;
        foreach ($rankable as $i => &$entry) {
            if ($i > 0) {
                $prev = $rankable[$i - 1];
                $sameKeys = $entry['total_tp'] === $prev['total_tp']
                    && $entry['total_diff'] === $prev['total_diff']
                    && $entry['total_vp'] === $prev['total_vp']
                    && (! $useSos || $entry['total_sos'] === $prev['total_sos']);
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
