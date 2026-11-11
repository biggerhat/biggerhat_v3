<?php

namespace App\Tournaments;

use App\Enums\FactionEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\User;
use App\Services\TournamentStandingsService;

/**
 * A person's tournament record for their public profile: every finished event they played (as a player or a team
 * member), where they placed, their win-loss record overall, per faction and against the people they met most.
 * Only completed events count — drafts and running events aren't public history yet.
 */
class TournamentHistory
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * @return array{
     *     summary: array{events: int, wins: int, podiums: int, games: int, won: int, lost: int, tied: int, win_rate: int|null, best_finish: int|null},
     *     by_faction: list<array{faction: string, label: string, games: int, won: int, lost: int, tied: int}>,
     *     rivals: list<array{name: string, slug: string|null, games: int, won: int, lost: int, tied: int}>,
     *     events: list<array<string, mixed>>
     * }
     */
    public function forUser(User $user): array
    {
        $entries = TournamentPlayer::query()
            ->forUser($user->id)
            ->whereHas('tournament', fn ($q) => $q->where('status', TournamentStatusEnum::Completed))
            ->with(['tournament', 'tournament.rounds.games.playerOne.user:id,name,slug', 'tournament.rounds.games.playerTwo.user:id,name,slug'])
            ->get()
            ->sortByDesc(fn (TournamentPlayer $e) => $e->tournament->event_date)
            ->values();

        $events = [];
        /** @var array<string, array{won: int, lost: int, tied: int}> $factions */
        $factions = [];
        /** @var array<string, array{name: string, slug: string|null, won: int, lost: int, tied: int}> $rivals */
        $rivals = [];
        $record = ['won' => 0, 'lost' => 0, 'tied' => 0];

        foreach ($entries as $entry) {
            /** @var Tournament $tournament */
            $tournament = $entry->tournament;
            $standings = collect($this->standings->compute($tournament));
            $row = $standings->firstWhere('player_id', $entry->id);

            $mine = ['won' => 0, 'lost' => 0, 'tied' => 0];
            foreach ($tournament->rounds as $round) {
                foreach ($round->games as $game) {
                    $outcome = $this->outcome($game, $entry->id);
                    if ($outcome === null) {
                        continue;
                    }
                    $mine[$outcome]++;
                    $record[$outcome]++;

                    $side = $game->player_one_id === $entry->id ? 'one' : 'two';
                    $faction = $game->{"player_{$side}_faction"} ?: $entry->getRawOriginal('faction');
                    if ($faction) {
                        $factions[$faction] ??= ['won' => 0, 'lost' => 0, 'tied' => 0];
                        $factions[$faction][$outcome]++;
                    }

                    $opponent = $side === 'one' ? $game->playerTwo : $game->playerOne;
                    if ($opponent !== null && $opponent->id !== $entry->id) {
                        $key = $opponent->user?->id ? 'u'.$opponent->user->id : 'n'.mb_strtolower($opponent->display_name);
                        $rivals[$key] ??= ['name' => (string) data_get($opponent, 'user.name', $opponent->display_name), 'slug' => data_get($opponent, 'user.slug'), 'won' => 0, 'lost' => 0, 'tied' => 0];
                        $rivals[$key][$outcome]++;
                    }
                }
            }

            $events[] = [
                'uuid' => $tournament->uuid,
                'name' => $tournament->name,
                'event_date' => $tournament->event_date->toDateString(),
                'ruleset' => $tournament->rulesetDefinition()->label,
                'is_team' => $tournament->isTeamEvent(),
                'team_name' => $tournament->isTeamEvent() ? $entry->display_name : null,
                'rank' => $row['rank'] ?? null,
                'of' => $standings->where('is_ringer', false)->count(),
                'tp' => $row['total_tp'] ?? 0,
                'won' => $mine['won'],
                'lost' => $mine['lost'],
                'tied' => $mine['tied'],
                'faction' => $entry->getRawOriginal('faction'),
            ];
        }

        $games = $record['won'] + $record['lost'] + $record['tied'];
        $ranks = collect($events)->pluck('rank')->filter();

        return [
            'summary' => [
                'events' => count($events),
                'wins' => $ranks->filter(fn ($r) => $r === 1)->count(),
                'podiums' => $ranks->filter(fn ($r) => $r <= 3)->count(),
                'games' => $games,
                ...$record,
                'win_rate' => $games > 0 ? (int) round($record['won'] / $games * 100) : null,
                'best_finish' => $ranks->isEmpty() ? null : (int) $ranks->min(),
            ],
            'by_faction' => collect($factions)
                ->map(fn (array $r, string $faction) => [
                    'faction' => $faction,
                    'label' => FactionEnum::tryFrom($faction)?->label() ?? $faction,
                    'games' => array_sum($r),
                    ...$r,
                ])
                ->sortByDesc('games')->values()->all(),
            'rivals' => collect($rivals)
                ->map(fn (array $r) => [...$r, 'games' => $r['won'] + $r['lost'] + $r['tied']])
                ->filter(fn (array $r) => $r['games'] >= 2)
                ->sortByDesc('games')->take(5)->values()->all(),
            'events' => $events,
        ];
    }

    /** The result of a played game from this competitor's side; byes and unfinished games don't count. */
    private function outcome(TournamentGame $game, int $playerId): ?string
    {
        if ($game->is_bye || ! in_array($playerId, [$game->player_one_id, $game->player_two_id], true)) {
            return null;
        }
        if (! in_array($game->result->value ?? $game->result, ['completed', 'agreed', 'forfeited'], true)) {
            return null;
        }
        if ($game->is_forfeit) {
            return (int) $game->forfeit_player_id === $playerId ? 'lost' : 'won';
        }

        $mine = $game->player_one_id === $playerId ? $game->player_one_vp : $game->player_two_vp;
        $theirs = $game->player_one_id === $playerId ? $game->player_two_vp : $game->player_one_vp;

        return $mine === $theirs ? 'tied' : ($mine > $theirs ? 'won' : 'lost');
    }
}
