<?php

namespace App\Tournaments\Analytics;

use App\Models\Scheme;
use App\Models\TournamentGame;
use App\Models\TournamentRound;
use Illuminate\Support\Facades\DB;

/**
 * Which strategies and schemes produced what, across any set of rounds.
 *
 * Strategies come from the rounds themselves, so they cover every game. Schemes only exist as per-turn rows on
 * Game Tracker games, so they cover just the pairings that were played through the tracker; `coverage` says how many.
 */
final class ScenarioStats
{
    /**
     * Per strategy: how many rounds used it and what the games under it scored.
     *
     * @param  iterable<TournamentRound>  $rounds  with `games` and `strategy` loaded
     * @return list<array{name: string, rounds: int, games: int, avg_vp: float|null, avg_margin: float|null, components: array<string, float|null>}>
     */
    public static function strategies(iterable $rounds): array
    {
        $by = [];
        foreach ($rounds as $round) {
            $name = $round->strategy?->name;
            if (! $name) {
                continue;
            }
            $by[$name] ??= ['rounds' => 0, 'vp' => [], 'margin' => [], 'components' => []];
            $by[$name]['rounds']++;
            foreach ($round->games as $game) {
                /** @var TournamentGame $game */
                if (! InsightMath::isContested($game)) {
                    continue;
                }
                $one = (int) ($game->player_one_vp ?? 0);
                $two = (int) ($game->player_two_vp ?? 0);
                array_push($by[$name]['vp'], $one, $two);
                $by[$name]['margin'][] = abs($one - $two);
                foreach (['player_one', 'player_two'] as $side) {
                    foreach ($game->breakdownFor($side) as $key => $value) {
                        $by[$name]['components'][$key][] = (int) $value;
                    }
                }
            }
        }

        return collect($by)
            ->map(fn (array $b, string $name) => [
                'name' => $name,
                'rounds' => $b['rounds'],
                'games' => intdiv(count($b['vp']), 2),
                'avg_vp' => InsightMath::average($b['vp']),
                'avg_margin' => InsightMath::average($b['margin']),
                'components' => array_map(fn (array $v) => InsightMath::average($v), $b['components']),
            ])
            ->sortBy([['games', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Per scheme, from the tracker turns of the given rounds' linked games.
     *
     * @param  iterable<TournamentRound>  $rounds  with `games` loaded
     * @return array{coverage: array{tracked: int, total: int}, rows: list<array{name: string, picked: int, scored: int, discarded: int, points: int, avg_points: float|null, score_rate: int|null}>}
     */
    public static function schemes(iterable $rounds): array
    {
        $games = collect($rounds)->flatMap(fn (TournamentRound $r) => $r->games)->filter(fn (TournamentGame $g) => InsightMath::isContested($g));
        $trackerIds = $games->pluck('game_id')->filter()->unique()->values()->all();
        $coverage = ['tracked' => $games->filter(fn (TournamentGame $g) => $g->game_id !== null)->count(), 'total' => $games->count()];

        if ($trackerIds === []) {
            return ['coverage' => $coverage, 'rows' => []];
        }

        $turns = DB::table('game_turns')
            ->whereIn('game_id', $trackerIds)
            ->whereNotNull('scheme_id')
            ->whereIn('scheme_action', ['scored', 'discarded'])
            ->selectRaw('scheme_id, scheme_action, COUNT(*) as n, SUM(scheme_points) as points')
            ->groupBy('scheme_id', 'scheme_action')
            ->get();

        $names = Scheme::whereIn('id', $turns->pluck('scheme_id')->unique())->pluck('name', 'id');
        /** @var array<string, array{name: string, scored: int, discarded: int, points: int}> $by */
        $by = [];
        foreach ($turns as $t) {
            $name = $names[$t->scheme_id] ?? null;
            if ($name === null) {
                continue;
            }
            $by[$name] ??= ['name' => $name, 'scored' => 0, 'discarded' => 0, 'points' => 0];
            if ($t->scheme_action === 'scored') {
                $by[$name]['scored'] += (int) $t->n;
            } else {
                $by[$name]['discarded'] += (int) $t->n;
            }
            $by[$name]['points'] += (int) $t->points;
        }

        $rows = collect($by)->map(function (array $b) {
            $picked = $b['scored'] + $b['discarded'];

            return $b + [
                'picked' => $picked,
                'avg_points' => $b['scored'] > 0 ? round($b['points'] / $b['scored'], 1) : null,
                'score_rate' => $picked > 0 ? (int) round($b['scored'] / $picked * 100) : null,
            ];
        })->sortBy([['picked', 'desc'], ['name', 'asc']])->values()->all();

        return ['coverage' => $coverage, 'rows' => $rows];
    }
}
