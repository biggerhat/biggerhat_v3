<?php

namespace App\Tournaments\Analytics;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentSeries;
use Illuminate\Support\Collection;

/**
 * The read-out across everything one organizer has run: turnout over time, how many players come back,
 * which factions show up and win, and who the regulars are. Draft and registration-stage events are left
 * out; they have no results to speak of.
 */
class OrganizerInsights
{
    public const RANGES = ['all' => 'All time', '12m' => 'Last 12 months'];

    /**
     * @return array<string, mixed>
     */
    public function forUser(int $userId, string $range = 'all'): array
    {
        $range = array_key_exists($range, self::RANGES) ? $range : 'all';

        $events = Tournament::forOrganizer($userId)
            ->whereIn('status', [TournamentStatusEnum::Active->value, TournamentStatusEnum::Completed->value])
            ->when($range === '12m', fn ($q) => $q->whereDate('event_date', '>=', now()->subYear()->toDateString()))
            ->with(['players.allegiance:id,slug,name', 'rounds.games', 'rounds.strategy:id,name'])
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        return ['range' => $range, 'ranges' => $this->ranges()] + $this->forEvents($events);
    }

    /**
     * The same read-out for a league: its active and finished events.
     *
     * @return array<string, mixed>
     */
    public function forSeries(TournamentSeries $series): array
    {
        $events = $series->tournaments()
            ->whereIn('status', [TournamentStatusEnum::Active->value, TournamentStatusEnum::Completed->value])
            ->with(['players.allegiance:id,slug,name', 'rounds.games', 'rounds.strategy:id,name'])
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        return $this->forEvents($events);
    }

    /** @return list<array{value: string, label: string}> */
    public function ranges(): array
    {
        return collect(self::RANGES)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    /**
     * @param  Collection<int, Tournament>  $events  oldest first, with players, rounds.games and rounds.strategy loaded
     * @return array<string, mixed>
     */
    public function forEvents(Collection $events): array
    {
        $seen = [];
        $attendance = [];
        $factionBuckets = [];
        $rows = [];
        $games = 0;
        $contested = 0;
        $margins = [];
        $totalEntries = 0;
        $rounds = 0;
        $rulesets = [];

        foreach ($events as $event) {
            /** @var Tournament $event */
            $real = $event->players->where('is_ringer', false)->where('is_disqualified', false);
            $returning = 0;
            $eventKeys = [];

            foreach ($real as $player) {
                $key = $this->identity($player);
                $eventKeys[$key] = true;
                $attendance[$key] ??= ['name' => $player->display_name, 'events' => 0, 'user_id' => $player->user_id];
                $attendance[$key]['events']++;
                if (isset($seen[$key])) {
                    $returning++;
                }
            }
            $seen += $eventKeys;

            $playersById = $event->players->keyBy('id');
            $eventGames = 0;
            foreach ($event->rounds as $round) {
                foreach ($round->games as $game) {
                    /** @var TournamentGame $game */
                    if (! InsightMath::isContested($game)) {
                        continue;
                    }
                    $eventGames++;
                    $one = (int) ($game->player_one_vp ?? 0);
                    $two = (int) ($game->player_two_vp ?? 0);
                    $margins[] = abs($one - $two);
                    foreach (['one', 'two'] as $side) {
                        $mine = $side === 'one' ? $one : $two;
                        $theirs = $side === 'one' ? $two : $one;
                        $playerId = $side === 'one' ? $game->player_one_id : $game->player_two_id;
                        if (! $event->isTeamEvent()) {
                            InsightMath::tally(
                                $factionBuckets,
                                InsightMath::sideLabel($event, $side === 'one' ? $game->player_one_faction : $game->player_two_faction, $playersById->get($playerId)),
                                $playerId,
                                $mine > $theirs ? 'win' : ($mine < $theirs ? 'loss' : 'tie'),
                            );
                        }
                    }
                }
            }
            $contested += $eventGames;
            $games += $eventGames;

            $label = $event->rulesetDefinition()->label;
            $rulesets[$label] = ($rulesets[$label] ?? 0) + 1;

            $roundCount = $event->rounds->where('is_cut', false)->filter(fn ($r) => $r->games->isNotEmpty())->count();
            $rounds += $roundCount;
            $totalEntries += $real->count();

            $rows[] = [
                'uuid' => $event->uuid,
                'name' => $event->name,
                'date' => $event->event_date->toDateString(),
                'status' => $event->status->value,
                'players' => $real->count(),
                'returning' => $returning,
                'new' => $real->count() - $returning,
                'rounds' => $roundCount,
                'games' => $eventGames,
                'ruleset' => $label,
            ];
        }

        $count = count($rows);
        $unique = count($attendance);
        $repeat = collect($attendance)->filter(fn (array $a) => $a['events'] > 1)->count();
        $returningTotal = array_sum(array_column($rows, 'returning'));

        return [
            'summary' => [
                'events' => $count,
                'completed' => collect($rows)->where('status', 'completed')->count(),
                'unique_players' => $unique,
                'entries' => $totalEntries,
                'avg_attendance' => $count > 0 ? round($totalEntries / $count, 1) : null,
                'largest' => $count > 0 ? max(array_column($rows, 'players')) : null,
                'avg_rounds' => $count > 0 ? round($rounds / $count, 1) : null,
                'games' => $games,
                'avg_margin' => InsightMath::average($margins),
                'repeat_players' => $repeat,
                'repeat_pct' => $unique > 0 ? (int) round($repeat / $unique * 100) : null,
                // Of everyone who entered an event after your first, how many had been to one of yours before.
                'returning_pct' => $totalEntries - ($rows[0]['players'] ?? 0) > 0
                    ? (int) round($returningTotal / ($totalEntries - $rows[0]['players']) * 100)
                    : null,
            ],
            'events' => $rows,
            'factions' => array_slice(InsightMath::rows($factionBuckets), 0, 14),
            'rulesets' => collect($rulesets)->map(fn (int $n, string $label) => ['label' => $label, 'events' => $n])->values()->all(),
            'regulars' => $this->regulars($attendance),
            'strategies' => ScenarioStats::strategies($events->flatMap->rounds),
            'schemes' => ScenarioStats::schemes($events->flatMap->rounds),
        ];
    }

    /** One person across events: their account when they have one, otherwise their name. */
    private function identity(TournamentPlayer $player): string
    {
        return $player->user_id ? 'u'.$player->user_id : 'n'.mb_strtolower(trim($player->display_name));
    }

    /**
     * @param  array<string, array{name: string, events: int, user_id: int|null}>  $attendance
     * @return list<array{name: string, events: int}>
     */
    private function regulars(array $attendance): array
    {
        return Collection::make($attendance)
            ->filter(fn (array $a) => $a['events'] > 1)
            ->sortBy([['events', 'desc'], ['name', 'asc']])
            ->take(8)
            ->map(fn (array $a) => ['name' => $a['name'], 'events' => $a['events']])
            ->values()
            ->all();
    }
}
