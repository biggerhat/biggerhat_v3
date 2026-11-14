<?php

namespace App\Tournaments\Analytics;

use App\Enums\FactionEnum;
use App\Enums\TournamentGameResultEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;

/**
 * Small shared helpers for the tournament analytics panels: which games count as a real contest, what a side
 * is called in a breakdown (faction, or Allegiance in TOS) and how results turn into a win rate.
 */
final class InsightMath
{
    /** A game with two players, a recorded result, and no bye or forfeit: the only kind that says anything about scores. */
    public static function isContested(TournamentGame $game): bool
    {
        return ! $game->is_bye
            && ! $game->is_forfeit
            && $game->player_two_id !== null
            && $game->result !== TournamentGameResultEnum::Pending;
    }

    /** What a side is grouped under in faction breakdowns. */
    public static function sideLabel(Tournament $tournament, ?string $gameFaction, ?TournamentPlayer $player): string
    {
        if ($tournament->rulesetDefinition()->declaration === 'allegiance') {
            $allegiance = $player?->allegiance;

            return $allegiance instanceof \App\Models\TOS\Allegiance ? $allegiance->name : 'Undeclared';
        }

        $raw = $gameFaction ?: $player?->getRawOriginal('faction');
        if (! $raw) {
            return 'Undeclared';
        }

        return FactionEnum::tryFrom($raw)?->label() ?? $raw;
    }

    /**
     * Win rate as a percentage, counting a tie as half a win. Null when there are no games to speak of.
     */
    public static function winRate(int $wins, int $ties, int $games): ?float
    {
        return $games > 0 ? round((($wins + 0.5 * $ties) / $games) * 100, 1) : null;
    }

    public static function average(array|\Illuminate\Support\Collection $values, int $precision = 1): ?float
    {
        $values = collect($values)->filter(fn ($v) => $v !== null)->values();

        return $values->isEmpty() ? null : round((float) $values->avg(), $precision);
    }

    /**
     * Tallies one side of a contested game into a breakdown bucket keyed by label.
     *
     * @param  array<string, array{label: string, players: array<int, true>, games: int, wins: int, losses: int, ties: int}>  $buckets
     */
    public static function tally(array &$buckets, string $label, ?int $playerId, string $outcome): void
    {
        $buckets[$label] ??= ['label' => $label, 'players' => [], 'games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0];
        $buckets[$label]['games']++;
        $buckets[$label][match ($outcome) {
            'win' => 'wins',
            'loss' => 'losses',
            default => 'ties',
        }]++;
        if ($playerId !== null) {
            $buckets[$label]['players'][$playerId] = true;
        }
    }

    /**
     * Turns tally buckets into rows sorted by games played, then name.
     *
     * @param  array<string, array{label: string, players: array<int, true>, games: int, wins: int, losses: int, ties: int}>  $buckets
     * @return list<array{label: string, players: int, games: int, wins: int, losses: int, ties: int, win_rate: float|null}>
     */
    public static function rows(array $buckets): array
    {
        return collect($buckets)
            ->map(fn (array $b) => [
                'label' => $b['label'],
                'players' => count($b['players']),
                'games' => $b['games'],
                'wins' => $b['wins'],
                'losses' => $b['losses'],
                'ties' => $b['ties'],
                'win_rate' => self::winRate($b['wins'], $b['ties'], $b['games']),
            ])
            ->sortBy([['games', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }
}
