<?php

namespace App\Support\Game;

use App\Enums\GameFormatEnum;
use App\Enums\GameStatusEnum;
use App\Models\Game;

/**
 * The order a game's setup goes through, in one place: which statuses it passes, what comes after each, and the steps the screen shows.
 * The controllers used to write the next status themselves at six different places; they now ask here, so a game format (or, later, a
 * play mode with its own Deployment step) changes the sequence by changing it here only.
 *
 * Standard and Campaign: Setup (waiting for an opponent) -> faction -> master -> crew -> scheme -> in progress.
 * Bonanza Brawl has one model and no scheme, so it goes from master straight to in progress.
 */
final class GameSetupFlow
{
    /** The steps the setup screen shows, in order: its key, the word on screen and the status a game has while it is on that step. */
    private const STEPS = [
        ['key' => 'faction', 'label' => 'Faction', 'status' => GameStatusEnum::FactionSelect],
        ['key' => 'master', 'label' => 'Master', 'status' => GameStatusEnum::MasterSelect],
        ['key' => 'crew', 'label' => 'Crew', 'status' => GameStatusEnum::CrewSelect],
        ['key' => 'scheme', 'label' => 'Scheme', 'status' => GameStatusEnum::SchemeSelect],
    ];

    /**
     * Every status before play, in order, for this game.
     *
     * @return list<GameStatusEnum>
     */
    public static function statuses(Game $game): array
    {
        $statuses = [GameStatusEnum::Setup, GameStatusEnum::FactionSelect, GameStatusEnum::MasterSelect];

        if ($game->format !== GameFormatEnum::BonanzaBrawl) {
            $statuses[] = GameStatusEnum::CrewSelect;
            $statuses[] = GameStatusEnum::SchemeSelect;
        }

        return $statuses;
    }

    /** Where a new game starts: a solo game has nobody to wait for, a duel waits for its opponent. */
    public static function startingStatus(bool $isSolo): GameStatusEnum
    {
        return $isSolo ? GameStatusEnum::FactionSelect : GameStatusEnum::Setup;
    }

    /** The status after `$from`, or null when `$from` is not a setup status of this game. The last step is followed by play. */
    public static function next(Game $game, GameStatusEnum $from): ?GameStatusEnum
    {
        $statuses = self::statuses($game);
        $at = array_search($from, $statuses, true);

        if ($at === false) {
            return null;
        }

        return $statuses[$at + 1] ?? GameStatusEnum::InProgress;
    }

    /**
     * Move a game on from `$from` to whatever comes next, and say whether it did. **Call it inside the transaction that holds the lock
     * on the game** (`Game::lockForUpdate()`): it re-checks the status itself, so a second request that arrives after the first has
     * already advanced the game changes nothing. Starting play also starts turn 1. `$extra` is written in the same update.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function advance(Game $locked, GameStatusEnum $from, array $extra = []): bool
    {
        if ($locked->status !== $from) {
            return false;
        }

        $next = self::next($locked, $from);
        if ($next === null) {
            return false;
        }

        $attributes = ['status' => $next, ...$extra];
        if ($next === GameStatusEnum::InProgress) {
            $attributes += ['current_turn' => 1];
        }

        $locked->update($attributes);

        return true;
    }

    /**
     * The steps for the setup indicator, so the screen does not guess status names from step names.
     *
     * @return list<array{key: string, label: string, status: string}>
     */
    public static function steps(Game $game): array
    {
        return array_map(
            fn (array $step) => ['key' => $step['key'], 'label' => $step['label'], 'status' => $step['status']->value],
            self::STEPS,
        );
    }
}
