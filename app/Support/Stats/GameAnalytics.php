<?php

namespace App\Support\Stats;

use Illuminate\Support\Collection;

/**
 * The numbers behind a player's analytics, worked out from a list of {@see PlayedGame}s (already filtered). Every block carries how many
 * games it is based on; a rate with fewer than {@see self::MIN_SAMPLE} games behind it is marked unreliable so the screen can show the
 * count instead. A game that lacks the data a block needs (no turns, no crew) is left out of that block only, and `coverage` says how many
 * were usable, so a block never claims more than the data shows.
 */
final class GameAnalytics
{
    /** Fewer games than this and a percentage is not worth showing. */
    public const MIN_SAMPLE = 5;

    /**
     * @param  Collection<int, PlayedGame>  $games
     * @param  array<string, mixed>  $f  the filters (see {@see self::filter()})
     * @return Collection<int, PlayedGame>
     */
    public function filter(Collection $games, array $f): Collection
    {
        $has = fn (string $k) => isset($f[$k]) && $f[$k] !== '' && $f[$k] !== [];
        $ci = fn (?string $a, string $b) => $a !== null && mb_strtolower($a) === mb_strtolower($b);

        return $games->filter(function (PlayedGame $g) use ($f, $has, $ci): bool {
            if ($has('from') && $g->date < $f['from']) {
                return false;
            }
            if ($has('to') && $g->date > $f['to']) {
                return false;
            }
            if ($has('sources') && ! in_array($g->source, (array) $f['sources'], true)) {
                return false;
            }
            if (array_key_exists('solo', $f) && ! $f['solo'] && $g->isSolo) {
                return false;
            }
            if ($has('result') && $g->result !== $f['result']) {
                return false;
            }
            if ($has('faction') && $g->faction !== $f['faction']) {
                return false;
            }
            if ($has('master') && ! $ci($g->master, (string) $f['master'])) {
                return false;
            }
            if ($has('opponent') && ! $ci($g->oppName, (string) $f['opponent'])) {
                return false;
            }
            if ($has('opponent_faction') && $g->oppFaction !== $f['opponent_faction']) {
                return false;
            }
            if ($has('strategy') && $g->strategy !== $f['strategy']) {
                return false;
            }
            if ($has('deployment') && $g->deployment !== $f['deployment']) {
                return false;
            }
            if ($has('size') && (string) $g->encounterSize !== (string) $f['size']) {
                return false;
            }
            if ($has('season') && $g->season !== $f['season']) {
                return false;
            }
            if ($has('play_mode') && $g->playMode !== $f['play_mode']) {
                return false;
            }
            if ($has('venue') && ! $ci($g->venue, (string) $f['venue'])) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * What the filters can be set to: the distinct values present in the person's games.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return array<string, list<string|int>>
     */
    public function options(Collection $games): array
    {
        $distinct = fn (callable $pick) => $games->map($pick)->filter(fn ($v) => $v !== null && $v !== '')->unique()->sort()->values()->all();

        return [
            'factions' => $distinct(fn (PlayedGame $g) => $g->faction),
            'opponent_factions' => $distinct(fn (PlayedGame $g) => $g->oppFaction),
            'masters' => $distinct(fn (PlayedGame $g) => $g->master),
            'opponents' => $distinct(fn (PlayedGame $g) => $g->oppName),
            'strategies' => $distinct(fn (PlayedGame $g) => $g->strategy),
            'deployments' => $distinct(fn (PlayedGame $g) => $g->deployment),
            'sizes' => $distinct(fn (PlayedGame $g) => $g->encounterSize),
            'seasons' => $distinct(fn (PlayedGame $g) => $g->season),
            'play_modes' => $distinct(fn (PlayedGame $g) => $g->playMode),
            'venues' => $distinct(fn (PlayedGame $g) => $g->venue),
        ];
    }

    /**
     * @param  Collection<int, PlayedGame>  $games
     * @return array<string, mixed>
     */
    public function compute(Collection $games): array
    {
        $chronological = $games->sortBy(fn (PlayedGame $g) => $g->date.$g->key)->values();

        return [
            'overview' => $this->overview($chronological),
            'trend' => ['monthly' => $this->monthly($chronological), 'rolling' => $this->rolling($chronological)],
            'by_faction' => $this->by($games, fn (PlayedGame $g) => $g->faction),
            'by_master' => $this->by($games, fn (PlayedGame $g) => $g->master),
            'by_role' => $this->by($games, fn (PlayedGame $g) => $g->role),
            'by_opponent_faction' => $this->by($games, fn (PlayedGame $g) => $g->oppFaction),
            'by_strategy' => $this->by($games, fn (PlayedGame $g) => $g->strategy),
            'by_deployment' => $this->by($games, fn (PlayedGame $g) => $g->deployment),
            'by_size' => $this->by($games, fn (PlayedGame $g) => $g->encounterSize),
            'by_season' => $this->by($games, fn (PlayedGame $g) => $g->season),
            'by_play_mode' => $this->by($games, fn (PlayedGame $g) => $g->playMode),
            'by_venue' => $this->by($games, fn (PlayedGame $g) => $g->venue),
            'by_source' => $this->by($games, fn (PlayedGame $g) => $g->source),
            'matchups' => $this->matchups($games),
            'master_matchups' => $this->masterMatchups($games),
            'opponents' => $this->opponents($games),
            'schemes' => $this->schemes($games),
            'turns' => $this->turnShape($games),
            'models' => $this->models($games),
            'coverage' => $this->coverage($games),
        ];
    }

    /**
     * The record behind a set of games.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return array{games: int, wins: int, losses: int, ties: int, win_rate: int|null, interval: array{low: int, high: int}|null, avg_vp: float|null, avg_opp_vp: float|null, reliable: bool}
     */
    public function tally(Collection $games): array
    {
        $n = $games->count();
        $wins = $games->where('result', 'win')->count();
        $ties = $games->where('result', 'tie')->count();

        return [
            'games' => $n,
            'wins' => $wins,
            'losses' => $n - $wins - $ties,
            'ties' => $ties,
            'win_rate' => $n > 0 ? (int) round($wins / $n * 100) : null,
            'interval' => $this->wilson($wins, $n),
            'avg_vp' => $n > 0 ? round($games->avg('vp'), 1) : null,
            'avg_opp_vp' => $n > 0 ? round($games->avg('oppVp'), 1) : null,
            'reliable' => $n >= self::MIN_SAMPLE,
        ];
    }

    /**
     * The 95% Wilson score interval for a win rate: honest about how little a handful of games says.
     *
     * @return array{low: int, high: int}|null
     */
    public function wilson(int $wins, int $n): ?array
    {
        if ($n === 0) {
            return null;
        }
        $z = 1.96;
        $p = $wins / $n;
        $denominator = 1 + $z * $z / $n;
        $centre = $p + $z * $z / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $n)) / $n);

        return ['low' => (int) round(max(0, ($centre - $margin) / $denominator) * 100), 'high' => (int) round(min(1, ($centre + $margin) / $denominator) * 100)];
    }

    /**
     * @param  Collection<int, PlayedGame>  $chronological
     * @return array<string, mixed>
     */
    private function overview(Collection $chronological): array
    {
        $tally = $this->tally($chronological);
        $results = $chronological->pluck('result')->all();

        $current = ['type' => null, 'count' => 0];
        for ($i = count($results) - 1; $i >= 0; $i--) {
            if ($current['type'] === null) {
                $current = ['type' => $results[$i], 'count' => 1];
            } elseif ($results[$i] === $current['type']) {
                $current['count']++;
            } else {
                break;
            }
        }
        $best = 0;
        $run = 0;
        foreach ($results as $r) {
            $run = $r === 'win' ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return [
            ...$tally,
            'total_vp' => (int) $chronological->sum('vp'),
            'avg_margin' => $tally['games'] > 0 ? round($chronological->avg(fn (PlayedGame $g) => $g->vp - $g->oppVp), 1) : null,
            'current_streak' => $current,
            'best_win_streak' => $best,
            'first_game' => $chronological->first()?->date,
            'last_game' => $chronological->last()?->date,
        ];
    }

    /**
     * @param  Collection<int, PlayedGame>  $chronological
     * @return list<array<string, mixed>>
     */
    private function monthly(Collection $chronological): array
    {
        return $chronological->groupBy(fn (PlayedGame $g) => substr($g->date, 0, 7))->map(fn (Collection $rows, string $month) => [
            'month' => $month,
            ...collect($this->tally($rows))->only(['games', 'wins', 'win_rate', 'avg_vp', 'reliable']),
        ])->values()->all();
    }

    /**
     * The win rate over the last ten games, after each game from the tenth on.
     *
     * @param  Collection<int, PlayedGame>  $chronological
     * @return list<array{date: string, win_rate: int}>
     */
    private function rolling(Collection $chronological): array
    {
        $out = [];
        $window = 10;
        for ($i = $window - 1; $i < $chronological->count(); $i++) {
            $slice = $chronological->slice($i - $window + 1, $window);
            $out[] = ['date' => $chronological[$i]->date, 'win_rate' => (int) round($slice->where('result', 'win')->count() / $window * 100)];
        }

        return array_slice($out, -60);
    }

    /**
     * One row per value of `$key`, most games first. A game with no value for it is left out.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function by(Collection $games, callable $key): array
    {
        return $games->filter(fn (PlayedGame $g) => $key($g) !== null && $key($g) !== '')
            ->groupBy(fn (PlayedGame $g) => (string) $key($g))
            ->map(fn (Collection $rows, string $label) => ['key' => $label, ...$this->tally($rows)])
            ->sortByDesc('games')->values()->all();
    }

    /**
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function matchups(Collection $games): array
    {
        return $games->filter(fn (PlayedGame $g) => $g->faction && $g->oppFaction && ! $g->isSolo)
            ->groupBy(fn (PlayedGame $g) => $g->faction.'|'.$g->oppFaction)
            ->map(fn (Collection $rows) => ['faction' => $rows[0]->faction, 'opponent_faction' => $rows[0]->oppFaction, ...$this->tally($rows)])
            ->sortByDesc('games')->values()->all();
    }

    /**
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function masterMatchups(Collection $games): array
    {
        return $games->filter(fn (PlayedGame $g) => $g->master && $g->oppMaster && ! $g->isSolo)
            ->groupBy(fn (PlayedGame $g) => mb_strtolower($g->master.'|'.$g->oppMaster))
            ->map(fn (Collection $rows) => ['master' => $rows[0]->master, 'opponent_master' => $rows[0]->oppMaster, ...$this->tally($rows)])
            ->sortByDesc('games')->values()->take(40)->all();
    }

    /**
     * Head to head against each opponent. Solo games (against yourself) are not an opponent.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function opponents(Collection $games): array
    {
        return $games->filter(fn (PlayedGame $g) => $g->oppName && ! $g->isSolo)
            ->groupBy(fn (PlayedGame $g) => mb_strtolower($g->oppName))
            ->map(fn (Collection $rows) => [
                'name' => $rows[0]->oppName,
                'slug' => $rows->pluck('oppSlug')->filter()->first(),
                'last_played' => $rows->max('date'),
                ...$this->tally($rows),
            ])->sortByDesc('games')->values()->take(40)->all();
    }

    /**
     * How each scheme was used: picked, scored, held over, or discarded, and what it earned. Only games with turns recorded.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function schemes(Collection $games): array
    {
        $rows = $games->flatMap(fn (PlayedGame $g) => collect($g->turns)->filter(fn ($t) => $t['scheme_name'] !== null)->map(fn ($t) => $t + ['won' => $g->result === 'win']));

        return $rows->groupBy('scheme_name')->map(function (Collection $turns, string $name) {
            $scored = $turns->filter(fn ($t) => $t['action'] === 'scored' || ($t['action'] === null && $t['scheme'] > 0));
            $picked = $turns->count();

            return [
                'name' => $name,
                'picked' => $picked,
                'scored' => $scored->count(),
                'held' => $turns->where('action', 'held')->count(),
                'discarded' => $turns->where('action', 'discarded')->count(),
                'score_rate' => $picked > 0 ? (int) round($scored->count() / $picked * 100) : null,
                'total_vp' => (int) $turns->sum('scheme'),
                'avg_vp_when_scored' => $scored->isEmpty() ? null : round($scored->avg('scheme'), 1),
                'reliable' => $picked >= self::MIN_SAMPLE,
            ];
        })->sortByDesc('picked')->values()->all();
    }

    /**
     * Average points per turn, for both sides, and how often scoring more in turn 1 went with winning.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return array<string, mixed>
     */
    private function turnShape(Collection $games): array
    {
        $withTurns = $games->filter(fn (PlayedGame $g) => $g->turns !== []);
        $byTurn = $withTurns->flatMap(fn (PlayedGame $g) => $g->turns)->groupBy('turn')->sortKeys()->map(fn (Collection $t, $turn) => [
            'turn' => (int) $turn,
            'games' => $t->count(),
            'strategy' => round($t->avg('strategy'), 1),
            'scheme' => round($t->avg('scheme'), 1),
            'opp_strategy' => round($t->avg('opp_strategy'), 1),
            'opp_scheme' => round($t->avg('opp_scheme'), 1),
        ])->values()->all();

        $first = $withTurns->filter(fn (PlayedGame $g) => collect($g->turns)->firstWhere('turn', 1) !== null)->map(function (PlayedGame $g) {
            $t = collect($g->turns)->firstWhere('turn', 1);

            return ['ahead' => ($t['strategy'] + $t['scheme']) > ($t['opp_strategy'] + $t['opp_scheme']), 'won' => $g->result === 'win'];
        });
        $ahead = $first->where('ahead', true);

        return [
            'by_turn' => $byTurn,
            'strategy_share' => $withTurns->isEmpty() ? null : (int) round($withTurns->sum(fn (PlayedGame $g) => collect($g->turns)->sum('strategy')) / max(1, $withTurns->sum(fn (PlayedGame $g) => collect($g->turns)->sum(fn ($t) => $t['strategy'] + $t['scheme']))) * 100),
            'turn_one_lead' => [
                'games' => $ahead->count(),
                'wins' => $ahead->where('won', true)->count(),
                'win_rate' => $ahead->isEmpty() ? null : (int) round($ahead->where('won', true)->count() / $ahead->count() * 100),
                'reliable' => $ahead->count() >= self::MIN_SAMPLE,
            ],
            'games' => $withTurns->count(),
        ];
    }

    /**
     * How often each model was hired and killed. Only games with a crew recorded.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return list<array<string, mixed>>
     */
    private function models(Collection $games): array
    {
        return $games->flatMap(fn (PlayedGame $g) => $g->models)->groupBy('name')->map(function (Collection $rows, string $name) {
            $hired = $rows->count();
            $killed = $rows->where('killed', true)->count();

            return [
                'name' => $name,
                'hired' => $hired,
                'killed' => $killed,
                'kill_rate' => (int) round($killed / $hired * 100),
                'avg_cost' => $rows->whereNotNull('cost')->isEmpty() ? null : round($rows->whereNotNull('cost')->avg('cost'), 1),
                'reliable' => $hired >= self::MIN_SAMPLE,
            ];
        })->sortByDesc('hired')->values()->take(30)->all();
    }

    /**
     * How many of the games each block could use.
     *
     * @param  Collection<int, PlayedGame>  $games
     * @return array<string, int>
     */
    private function coverage(Collection $games): array
    {
        return [
            'games' => $games->count(),
            'with_turns' => $games->filter(fn (PlayedGame $g) => $g->turns !== [])->count(),
            'with_models' => $games->filter(fn (PlayedGame $g) => $g->models !== [])->count(),
            'with_master' => $games->filter(fn (PlayedGame $g) => $g->master !== null)->count(),
            'with_strategy' => $games->filter(fn (PlayedGame $g) => $g->strategy !== null)->count(),
            'with_role' => $games->filter(fn (PlayedGame $g) => $g->role !== null)->count(),
            'with_opponent_faction' => $games->filter(fn (PlayedGame $g) => $g->oppFaction !== null)->count(),
        ];
    }
}
