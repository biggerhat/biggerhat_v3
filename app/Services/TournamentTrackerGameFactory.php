<?php

namespace App\Services;

use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRound;

/**
 * Bridges the Tournament tracker and the (separate) Game tracker.
 *
 * For every paired tournament game with at least one BiggerHat-linked player,
 * we lazily create a Game tracker game so participants can score in real-time
 * and spectators can observe. Manually-added (no `user_id`) opponents become
 * `opponent_name` strings on the Game tracker, mirroring how the Game tracker
 * already represents solo / non-account opponents.
 */
class TournamentTrackerGameFactory
{
    /**
     * Create Game tracker games for any not-yet-linked, non-bye games in this round.
     */
    public function createForRound(Tournament $tournament, TournamentRound $round): void
    {
        $games = $round->games()
            ->where('is_bye', false)
            ->whereNull('game_id')
            ->with(['playerOne', 'playerTwo'])
            ->get();

        foreach ($games as $tournamentGame) {
            /** @var TournamentGame $tournamentGame */
            $this->createForGame($tournament, $round, $tournamentGame);
        }
    }

    /**
     * Create a single Game tracker game and link the tournament game to it.
     * No-op if there's no BiggerHat-linked player on either side, or if the
     * tournament game is already linked.
     */
    private function createForGame(Tournament $tournament, TournamentRound $round, TournamentGame $tournamentGame): void
    {
        if ($tournamentGame->game_id) {
            return;
        }

        $playerOne = $tournamentGame->playerOne;
        $playerTwo = $tournamentGame->playerTwo;
        if (! $playerOne || ! $playerTwo) {
            return;
        }

        $hasUserOne = (bool) $playerOne->user_id;
        $hasUserTwo = (bool) $playerTwo->user_id;

        // Need at least one BiggerHat user to create a game
        if (! $hasUserOne && ! $hasUserTwo) {
            return;
        }

        $isSolo = ! $hasUserOne || ! $hasUserTwo;
        $creatorUserId = $hasUserOne ? $playerOne->user_id : $playerTwo->user_id;

        // Factions are already known from tournament registration, so start at MasterSelect
        $trackerGame = Game::create([
            'name' => "{$tournament->name} R{$round->round_number} T{$tournamentGame->table_number}",
            'encounter_size' => $tournament->encounter_size,
            'season' => $tournament->season->value,
            'strategy_id' => $round->strategy_id,
            'deployment' => $round->deployment?->value,
            'scheme_pool' => $round->scheme_pool ?? [],
            'status' => GameStatusEnum::MasterSelect,
            'started_at' => now(),
            'creator_id' => $creatorUserId,
            'is_solo' => $isSolo,
            'is_observable' => true,
        ]);

        $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

        GamePlayer::create([
            'game_id' => $trackerGame->id,
            'user_id' => $playerOne->user_id,
            'slot' => 1,
            'role' => $roles[0],
            'faction' => $playerOne->getRawOriginal('faction'),
            'opponent_name' => ! $hasUserOne ? $playerOne->display_name : null,
            'scheme_pool' => $trackerGame->scheme_pool,
        ]);

        GamePlayer::create([
            'game_id' => $trackerGame->id,
            'user_id' => $playerTwo->user_id,
            'slot' => 2,
            'role' => $roles[1],
            'faction' => $playerTwo->getRawOriginal('faction'),
            'opponent_name' => ! $hasUserTwo ? $playerTwo->display_name : null,
            'scheme_pool' => $trackerGame->scheme_pool,
        ]);

        $tournamentGame->update(['game_id' => $trackerGame->id]);
    }

    /**
     * Tear down any tracker games linked to this round (for re-pairing or deletion).
     */
    public function destroyForRound(TournamentRound $round): void
    {
        $linkedGameIds = $round->games()->whereNotNull('game_id')->pluck('game_id');
        if ($linkedGameIds->isEmpty()) {
            return;
        }

        GamePlayer::whereIn('game_id', $linkedGameIds)->delete();
        Game::whereIn('id', $linkedGameIds)->forceDelete();
    }

    /**
     * Tear down the single tracker game linked to this tournament game.
     */
    public function destroyForGame(TournamentGame $game): void
    {
        if (! $game->game_id) {
            return;
        }

        GamePlayer::where('game_id', $game->game_id)->delete();
        Game::where('id', $game->game_id)->forceDelete();
    }
}
