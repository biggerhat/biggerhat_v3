<?php

namespace App\Services;

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
use App\Models\Tournament;
use App\Services\Standings\PlayerStatsCalculator;
use App\Services\Standings\StandingsRanker;
use App\Services\Standings\StrengthOfScheduleCalculator;

/**
 * Orchestrates the standings pipeline:
 *   1. Per-player stats (TP/DIFF/VP/rounds + opponent IDs) — PlayerStatsCalculator
 *   2. Strength of Schedule annotation                     — StrengthOfScheduleCalculator
 *   3. Tiebreaker-aware sort + joint placing               — StandingsRanker
 *
 * Each step is independently testable. This class owns the data fetching
 * and the orchestration only.
 */
class TournamentStandingsService
{
    public function __construct(
        private readonly PlayerStatsCalculator $stats = new PlayerStatsCalculator,
        private readonly StrengthOfScheduleCalculator $sos = new StrengthOfScheduleCalculator,
        private readonly StandingsRanker $ranker = new StandingsRanker,
    ) {}

    /**
     * Compute standings for all eligible players in a tournament.
     *
     * @return array<int, array<string, mixed>>
     */
    public function compute(Tournament $tournament): array
    {
        $tournament->loadMissing(['players', 'rounds.games']);

        $players = $tournament->players->where('is_disqualified', false);
        $allGames = $tournament->rounds->flatMap(fn ($round) => $round->games);

        // Bye scoring is configurable per tournament (defaults to 3/4/6).
        $byeScoring = [
            'tp' => (int) ($tournament->bye_tp ?? 3),
            'diff' => (int) ($tournament->bye_diff ?? 4),
            'vp' => (int) ($tournament->bye_vp ?? 6),
        ];

        // Completed rounds drive the missed-round penalty. Build an id→number
        // lookup once so per-player stats can translate game.tournament_round_id
        // back to a round number in O(1).
        $completedRounds = $tournament->rounds
            ->filter(fn ($r) => $r->status === TournamentRoundStatusEnum::Completed)
            ->all();
        $completedRoundNumbers = [];
        foreach ($completedRounds as $cr) {
            $completedRoundNumbers[$cr->id] = $cr->round_number;
        }

        // Pre-index games by player so per-player stats computation is O(games_for_player)
        // instead of O(all_games). Matters on large fields (64p × 5r ≈ 160 games).
        $gamesByPlayer = [];
        foreach ($allGames as $g) {
            if ($g->player_one_id !== null) {
                $gamesByPlayer[$g->player_one_id][] = $g;
            }
            if ($g->player_two_id !== null) {
                $gamesByPlayer[$g->player_two_id][] = $g;
            }
        }

        // Pass 1: per-player base stats + opponent map.
        $standings = [];
        $opponentsByPlayer = [];
        foreach ($players as $player) {
            $playerGames = $gamesByPlayer[$player->id] ?? [];
            $stats = $this->stats->compute($player, $playerGames, $byeScoring, $completedRounds, $completedRoundNumbers);
            $standings[] = [
                'player_id' => $player->id,
                'display_name' => $player->display_name,
                'user_id' => $player->user_id,
                'faction' => $player->getRawOriginal('faction'),
                'is_ringer' => $player->is_ringer,
                'is_dropped' => $player->dropped_after_round !== null,
                'total_tp' => $stats['tp'],
                'total_diff' => $stats['diff'],
                'total_vp' => $stats['vp'],
                'rounds_played' => $stats['rounds'],
                'has_bye' => $stats['has_bye'],
                'wins' => $stats['wins'],
                'losses' => $stats['losses'],
                'ties' => $stats['ties'],
            ];
            $opponentsByPlayer[$player->id] = $stats['opponents'];
        }

        // Pass 2: SoS annotation.
        $standings = $this->sos->annotate($standings, $opponentsByPlayer);

        // Pass 3: sort + joint placing per the tournament's tiebreaker mode.
        $tiebreaker = $tournament->tiebreaker_mode ?? TournamentTiebreakerEnum::DiffVp;

        return $this->ranker->rank($standings, $tiebreaker);
    }
}
