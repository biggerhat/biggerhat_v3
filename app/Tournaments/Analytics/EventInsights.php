<?php

namespace App\Tournaments\Analytics;

use App\Enums\TournamentRoundStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;

/**
 * The organizer's read-out for one event: who turned up, how the games went, which factions and leaders did well,
 * and how each round ran. Everything is worked out from results already recorded; nothing here is stored.
 */
class EventInsights
{
    /** A game decided by this many points or fewer counts as close. */
    public const CLOSE_MARGIN = 2;

    /**
     * @param  array<int, array<string, mixed>>  $standings  the event's computed standings (so they are not worked out twice)
     * @return array<string, mixed>
     */
    public function forEvent(Tournament $tournament, array $standings): array
    {
        $tournament->loadMissing(['players.allegiance:id,slug,name', 'rounds.games', 'rounds.strategy:id,name', 'rsvps']);

        $ruleset = $tournament->rulesetDefinition();
        $playersById = $tournament->players->keyBy('id');
        $isTeam = $tournament->isTeamEvent();

        $factionBuckets = [];
        $leaderBuckets = [];
        $margins = [];
        $totals = [];
        $componentValues = [];
        $roundRows = [];
        $biggest = null;
        $highScore = null;
        $byes = 0;
        $forfeits = 0;
        $ties = 0;

        foreach ($tournament->rounds->sortBy('round_number') as $round) {
            /** @var TournamentRound $round */
            $roundMargins = [];
            $roundTotals = [];
            $roundForfeits = 0;
            $contested = 0;

            foreach ($round->games as $game) {
                /** @var TournamentGame $game */
                if ($game->is_bye && $game->result->value !== 'pending') {
                    $byes++;
                }
                if ($game->is_forfeit) {
                    $forfeits++;
                    $roundForfeits++;
                }
                if (! InsightMath::isContested($game)) {
                    continue;
                }

                $one = (int) ($game->player_one_vp ?? 0);
                $two = (int) ($game->player_two_vp ?? 0);
                $margin = abs($one - $two);
                $contested++;
                $margins[] = $margin;
                $roundMargins[] = $margin;
                array_push($totals, $one, $two);
                array_push($roundTotals, $one, $two);
                if ($one === $two) {
                    $ties++;
                }

                $winnerIsOne = $one >= $two;
                if ($margin > 0 && ($biggest === null || $margin > $biggest['margin'])) {
                    $biggest = $this->gameLine($round, $game, $playersById, $margin);
                }
                $best = max($one, $two);
                if ($highScore === null || $best > $highScore['vp']) {
                    $highScore = $this->gameLine($round, $game, $playersById, $margin) + ['vp' => $best, 'by' => $winnerIsOne ? 'one' : 'two'];
                }

                foreach (['one', 'two'] as $side) {
                    $mine = $side === 'one' ? $one : $two;
                    $theirs = $side === 'one' ? $two : $one;
                    $outcome = $mine > $theirs ? 'win' : ($mine < $theirs ? 'loss' : 'tie');
                    $playerId = $side === 'one' ? $game->player_one_id : $game->player_two_id;
                    $player = $playersById->get($playerId);

                    if (! $isTeam) {
                        InsightMath::tally(
                            $factionBuckets,
                            InsightMath::sideLabel($tournament, $side === 'one' ? $game->player_one_faction : $game->player_two_faction, $player),
                            $playerId,
                            $outcome,
                        );
                    }

                    $leader = $side === 'one' ? $game->player_one_master : $game->player_two_master;
                    if ($ruleset->tracksLeaders && $leader) {
                        InsightMath::tally($leaderBuckets, $leader, $playerId, $outcome);
                    }

                    foreach ($game->breakdownFor($side === 'one' ? 'player_one' : 'player_two') as $key => $value) {
                        $componentValues[$key][] = (int) $value;
                    }
                }
            }

            $roundRows[] = [
                'round_number' => $round->round_number,
                'label' => $round->label,
                'status' => $round->status->value,
                'games' => $contested,
                'avg_vp' => InsightMath::average($roundTotals),
                'avg_margin' => InsightMath::average($roundMargins),
                'forfeits' => $roundForfeits,
                'minutes' => $this->minutes($round),
                'strategy' => $round->strategy?->name,
                'deployment' => $round->deployment?->label(),
            ];
        }

        $contestedCount = count($margins);
        $close = count(array_filter($margins, fn (int $m) => $m <= self::CLOSE_MARGIN));

        $labels = collect($ruleset->scoreFields)->pluck('label', 'key');
        $components = collect($componentValues)
            ->map(fn (array $values, string $key) => ['key' => $key, 'label' => $labels->get($key, ucfirst($key)), 'avg' => InsightMath::average($values)])
            ->values()
            ->all();

        $playedIds = $tournament->rounds->flatMap->games
            ->filter(fn (TournamentGame $g) => $g->result->value !== 'pending')
            ->flatMap(fn (TournamentGame $g) => [$g->player_one_id, $g->player_two_id])
            ->filter()
            ->unique();

        return [
            'is_team' => $isTeam,
            'field' => $this->field($tournament, $playedIds->count()),
            'games' => [
                'contested' => $contestedCount,
                'byes' => $byes,
                'forfeits' => $forfeits,
                'ties' => $ties,
                'avg_vp' => InsightMath::average($totals),
                'avg_margin' => InsightMath::average($margins),
                'close' => $close,
                'close_pct' => $contestedCount > 0 ? (int) round($close / $contestedCount * 100) : null,
                'margin_buckets' => $this->marginBuckets($margins),
                'components' => $components,
            ],
            'rounds' => $roundRows,
            'time_limit' => $tournament->round_time_limit,
            'avg_round_minutes' => InsightMath::average(collect($roundRows)->pluck('minutes')->filter(), 0),
            'factions' => InsightMath::rows($factionBuckets),
            'leaders' => array_slice(InsightMath::rows($leaderBuckets), 0, 12),
            'strategies' => ScenarioStats::strategies($tournament->rounds),
            'schemes' => ScenarioStats::schemes($tournament->rounds),
            'tracks_leaders' => $ruleset->tracksLeaders,
            'highlights' => [
                'biggest_win' => $biggest,
                'high_score' => $highScore,
                'undefeated' => collect($standings)
                    ->filter(fn (array $s) => ! $s['is_ringer'] && $s['wins'] > 0 && $s['losses'] === 0 && $s['ties'] === 0)
                    ->pluck('display_name')
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Turnout: how many signed up, turned up, stayed, and how many were new to the organizer's events.
     *
     * @return array<string, int|null>
     */
    private function field(Tournament $tournament, int $played): array
    {
        $players = $tournament->players;
        $real = $players->where('is_ringer', false);
        $userIds = $real->pluck('user_id')->filter()->unique()->values();

        $returning = 0;
        if ($userIds->isNotEmpty()) {
            $returning = TournamentPlayer::query()
                ->whereIn('user_id', $userIds)
                ->where('tournament_id', '!=', $tournament->id)
                ->whereHas('tournament', fn ($q) => $q->whereDate('event_date', '<', $tournament->event_date))
                ->distinct()
                ->count('user_id');
        }

        return [
            'rsvps' => $tournament->rsvps->count(),
            'registered' => $players->count(),
            'checked_in' => $players->whereNotNull('checked_in_at')->count(),
            'played' => $played,
            'dropped' => $players->whereNotNull('dropped_after_round')->count(),
            'disqualified' => $players->where('is_disqualified', true)->count(),
            'ringers' => $players->where('is_ringer', true)->count(),
            // Only accounts can be recognised from event to event, so guests are left out of both figures.
            'with_accounts' => $userIds->count(),
            'returning' => $returning,
            'new' => $userIds->count() - $returning,
        ];
    }

    /** @return list<array{label: string, count: int}> */
    private function marginBuckets(array $margins): array
    {
        $buckets = [['label' => 'Tie', 'count' => 0], ['label' => '1 to 2', 'count' => 0], ['label' => '3 to 5', 'count' => 0], ['label' => '6 to 9', 'count' => 0], ['label' => '10 or more', 'count' => 0]];
        foreach ($margins as $m) {
            $i = match (true) {
                $m === 0 => 0,
                $m <= 2 => 1,
                $m <= 5 => 2,
                $m <= 9 => 3,
                default => 4,
            };
            $buckets[$i]['count']++;
        }

        return $buckets;
    }

    private function minutes(TournamentRound $round): ?int
    {
        if ($round->status !== TournamentRoundStatusEnum::Completed || ! $round->started_at || ! $round->completed_at) {
            return null;
        }
        $minutes = (int) $round->started_at->diffInMinutes($round->completed_at, true);

        // A round left open overnight says nothing about how long games take.
        return $minutes > 0 && $minutes <= 12 * 60 ? $minutes : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TournamentPlayer>  $playersById
     * @return array{round: string, table: int|null, winner: string, loser: string, winner_vp: int, loser_vp: int, margin: int}
     */
    private function gameLine(TournamentRound $round, TournamentGame $game, $playersById, int $margin): array
    {
        $one = (int) ($game->player_one_vp ?? 0);
        $two = (int) ($game->player_two_vp ?? 0);
        $oneWon = $one >= $two;
        $name = function (?int $id) use ($playersById): string {
            $player = $playersById->get($id);

            return $player instanceof TournamentPlayer ? $player->display_name : 'Unknown';
        };

        return [
            'round' => $round->label,
            'table' => $game->table_number,
            'winner' => $name($oneWon ? $game->player_one_id : $game->player_two_id),
            'loser' => $name($oneWon ? $game->player_two_id : $game->player_one_id),
            'winner_vp' => max($one, $two),
            'loser_vp' => min($one, $two),
            'margin' => $margin,
        ];
    }
}
