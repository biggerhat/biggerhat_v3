<?php

namespace App\Tournaments;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentSeries;
use App\Services\TournamentStandingsService;

/**
 * The league table of a series: every finished individual event in it awards points by finishing place
 * (the series' points table, then the participation points for anyone who played but placed lower), and a
 * player's total is the sum of their best N events when the series says so.
 *
 * Players are the same person across events when they are linked to the same account; players without an account
 * are matched by name (case-insensitive), and a name that matches a linked player's name joins that player.
 */
class SeriesStandings
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * @return array{events: list<array{id: int, uuid: string, name: string, event_date: string}>, rows: list<array<string, mixed>>}
     */
    public function compute(TournamentSeries $series): array
    {
        $events = $series->tournaments()->where('status', TournamentStatusEnum::Completed->value)->get()
            ->reject(fn (Tournament $t) => $t->isTeamEvent())
            ->values();

        // Pass 1: every placing, and which names belong to an account.
        $placings = [];
        $accountByName = [];
        foreach ($events as $event) {
            foreach ($this->standings->compute($event) as $row) {
                if ($row['is_ringer'] || $row['rank'] === null || ($row['rounds_played'] ?? 0) < 1) {
                    continue;
                }
                $placings[] = ['event' => $event, 'row' => $row];
                if ($row['user_id']) {
                    $accountByName[mb_strtolower(trim((string) $row['display_name']))] ??= 'u'.$row['user_id'];
                }
            }
        }

        // Pass 2: group by person and score each placing.
        $people = [];
        foreach ($placings as ['event' => $event, 'row' => $row]) {
            $name = trim((string) $row['display_name']);
            $key = $row['user_id'] ? 'u'.$row['user_id'] : ($accountByName[mb_strtolower($name)] ?? 'n:'.mb_strtolower($name));
            $rank = (int) $row['rank'];
            $points = $series->points_table[$rank - 1] ?? $series->participation_points;

            $people[$key] ??= ['key' => $key, 'name' => $name, 'user_id' => $row['user_id'], 'results' => []];
            $people[$key]['name'] = $name;
            $people[$key]['user_id'] ??= $row['user_id'];
            $people[$key]['results'][] = ['tournament_id' => $event->id, 'rank' => $rank, 'points' => (int) $points];
        }

        $rows = [];
        foreach ($people as $person) {
            // The best results count (all of them unless the series keeps only the best N).
            $best = $person['results'];
            usort($best, fn (array $a, array $b) => [$b['points'], $a['rank']] <=> [$a['points'], $b['rank']]);
            $counted = array_slice($best, 0, $series->best_of ?? count($best));
            $countedEvents = array_column($counted, 'tournament_id');
            $results = array_map(fn (array $r) => $r + ['counts' => in_array($r['tournament_id'], $countedEvents, true)], $person['results']);

            $ranks = array_column($person['results'], 'rank');
            $rows[] = [
                'key' => $person['key'],
                'name' => $person['name'],
                'user_id' => $person['user_id'],
                'total' => array_sum(array_column($counted, 'points')),
                'events_played' => count($person['results']),
                'wins' => count(array_filter($ranks, fn (int $r) => $r === 1)),
                'best_finish' => min($ranks),
                'results' => $results,
            ];
        }

        usort($rows, fn (array $a, array $b) => [$b['total'], $b['wins'], $a['best_finish'], $b['events_played'], $a['name']]
            <=> [$a['total'], $a['wins'], $b['best_finish'], $a['events_played'], $b['name']]);

        $previous = null;
        foreach ($rows as $i => &$row) {
            $signature = [$row['total'], $row['wins'], $row['best_finish']];
            $row['rank'] = $previous !== null && $previous['signature'] === $signature ? $previous['rank'] : $i + 1;
            $previous = ['signature' => $signature, 'rank' => $row['rank']];
        }
        unset($row);

        return [
            'events' => $events->map(fn (Tournament $t) => ['id' => $t->id, 'uuid' => $t->uuid, 'name' => $t->name, 'event_date' => $t->event_date->toDateString()])->all(),
            'rows' => $rows,
        ];
    }
}
