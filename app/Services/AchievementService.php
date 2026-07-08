<?php

namespace App\Services;

use App\Enums\GameStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\GamePlayer;
use App\Models\TournamentPlayer;

/**
 * Shared achievement/record computation used by both the public Profile/Stats
 * page and the private Settings Overview dashboard, so badge logic and
 * win/loss/tournament tallies aren't duplicated between the two.
 */
class AchievementService
{
    /**
     * Lightweight win/loss tally — deliberately NOT shared with
     * StatsController::show(), which fuses the same loop into a much larger
     * per-game pass (VP totals, faction/master/scheme breakdowns, matchups)
     * that the dashboard doesn't need.
     *
     * @return array{total_games: int, wins: int}
     */
    public function gameRecordForUser(int $userId): array
    {
        $playerRecords = GamePlayer::where('user_id', $userId)
            ->whereHas('game', fn ($q) => $q->where('status', GameStatusEnum::Completed))
            ->with('game:id,is_solo,is_tie,winner_id,winner_slot')
            ->get();

        $wins = $playerRecords->filter(function (GamePlayer $pr) use ($userId) {
            $game = $pr->game;

            return ! $game->is_tie && ($game->winner_id === $userId || ($game->is_solo && $game->winner_slot === $pr->slot));
        })->count();

        return [
            'total_games' => $playerRecords->count(),
            'wins' => $wins,
        ];
    }

    /**
     * @return array{played: int, best_finish: int|null}
     */
    public function tournamentRecordForUser(int $userId): array
    {
        $tournamentEntries = TournamentPlayer::where('user_id', $userId)
            ->whereHas('tournament', fn ($q) => $q->where('status', TournamentStatusEnum::Completed))
            ->with('tournament')
            ->get();

        $bestFinish = null;
        foreach ($tournamentEntries as $entry) {
            try {
                $standings = app(TournamentStandingsService::class)->compute($entry->tournament);
                $rank = collect($standings)->firstWhere('player_id', $entry->id);
                if ($rank && ($bestFinish === null || $rank['rank'] < $bestFinish)) {
                    $bestFinish = $rank['rank'];
                }
            } catch (\Throwable) {
                // Skip if standings fail
            }
        }

        return [
            'played' => $tournamentEntries->count(),
            'best_finish' => $bestFinish,
        ];
    }

    /**
     * @return array<int, array{icon: string, label: string, description: string}>
     */
    public function computeBadges(int $games, int $wins, int $collection, int $built, int $painted, int $tournaments, ?int $bestFinish, int $crews): array
    {
        $badges = [];

        // Game milestones
        if ($games >= 1) {
            $badges[] = ['icon' => 'swords', 'label' => 'First Blood', 'description' => 'Completed first game'];
        }
        if ($games >= 10) {
            $badges[] = ['icon' => 'swords', 'label' => 'Veteran', 'description' => '10 games completed'];
        }
        if ($games >= 50) {
            $badges[] = ['icon' => 'swords', 'label' => 'War Machine', 'description' => '50 games completed'];
        }
        if ($games >= 100) {
            $badges[] = ['icon' => 'swords', 'label' => 'Centurion', 'description' => '100 games completed'];
        }

        // Win milestones
        if ($wins >= 1) {
            $badges[] = ['icon' => 'trophy', 'label' => 'Victor', 'description' => 'First victory'];
        }
        if ($wins >= 10) {
            $badges[] = ['icon' => 'trophy', 'label' => 'Champion', 'description' => '10 victories'];
        }
        if ($wins >= 25) {
            $badges[] = ['icon' => 'trophy', 'label' => 'Conquerer', 'description' => '25 victories'];
        }

        // Collection milestones
        if ($collection >= 1) {
            $badges[] = ['icon' => 'package', 'label' => 'Collector', 'description' => 'Added first miniature to collection'];
        }
        if ($collection >= 10) {
            $badges[] = ['icon' => 'package', 'label' => 'Growing Pile', 'description' => '10 miniatures collected'];
        }
        if ($collection >= 25) {
            $badges[] = ['icon' => 'package', 'label' => 'Shelf Space', 'description' => '25 miniatures collected'];
        }
        if ($collection >= 50) {
            $badges[] = ['icon' => 'package', 'label' => 'Hoarder', 'description' => '50 miniatures collected'];
        }
        if ($collection >= 100) {
            $badges[] = ['icon' => 'package', 'label' => 'Warehouse', 'description' => '100 miniatures collected'];
        }
        if ($collection >= 200) {
            $badges[] = ['icon' => 'package', 'label' => 'Dragon\'s Hoard', 'description' => '200 miniatures collected'];
        }

        // Assembly milestones
        if ($built >= 1) {
            $badges[] = ['icon' => 'hammer', 'label' => 'Builder', 'description' => 'Assembled first miniature'];
        }
        if ($built >= 10) {
            $badges[] = ['icon' => 'hammer', 'label' => 'Assembler', 'description' => '10 miniatures assembled'];
        }
        if ($built >= 25) {
            $badges[] = ['icon' => 'hammer', 'label' => 'Craftsman', 'description' => '25 miniatures assembled'];
        }
        if ($built >= 50) {
            $badges[] = ['icon' => 'hammer', 'label' => 'Forgemaster', 'description' => '50 miniatures assembled'];
        }
        if ($built >= 100) {
            $badges[] = ['icon' => 'hammer', 'label' => 'Assembly Line', 'description' => '100 miniatures assembled'];
        }
        if ($collection > 0 && $built === $collection) {
            $badges[] = ['icon' => 'hammer', 'label' => 'No Shame Pile', 'description' => 'Every model assembled — no backlog!'];
        }

        // Painting milestones
        if ($painted >= 1) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'First Coat', 'description' => 'Painted first miniature'];
        }
        if ($painted >= 10) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Painter', 'description' => '10 miniatures painted'];
        }
        if ($painted >= 25) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Brush Warrior', 'description' => '25 miniatures painted'];
        }
        if ($painted >= 50) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Artist', 'description' => '50 miniatures painted'];
        }
        if ($painted >= 100) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Master Painter', 'description' => '100 miniatures painted'];
        }
        if ($collection > 0 && $painted === $collection) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Fully Painted', 'description' => 'Every model painted — the dream!'];
        }
        if ($collection >= 10 && $painted > 0 && $painted >= (int) ($collection * 0.75)) {
            $badges[] = ['icon' => 'paintbrush', 'label' => 'Paint Devotee', 'description' => '75%+ of collection painted'];
        }

        // Tournament
        if ($tournaments >= 1) {
            $badges[] = ['icon' => 'crown', 'label' => 'Competitor', 'description' => 'Entered a tournament'];
        }
        if ($tournaments >= 5) {
            $badges[] = ['icon' => 'crown', 'label' => 'Regular', 'description' => '5 tournaments played'];
        }
        if ($bestFinish !== null && $bestFinish === 1) {
            $badges[] = ['icon' => 'crown', 'label' => 'Tournament Champion', 'description' => 'Won a tournament'];
        }
        if ($bestFinish !== null && $bestFinish <= 3) {
            $badges[] = ['icon' => 'medal', 'label' => 'Podium Finish', 'description' => 'Top 3 in a tournament'];
        }

        // Community
        if ($crews >= 1) {
            $badges[] = ['icon' => 'share', 'label' => 'Strategist', 'description' => 'Shared a crew build'];
        }
        if ($crews >= 5) {
            $badges[] = ['icon' => 'share', 'label' => 'Theorist', 'description' => '5 shared crew builds'];
        }

        return $badges;
    }
}
