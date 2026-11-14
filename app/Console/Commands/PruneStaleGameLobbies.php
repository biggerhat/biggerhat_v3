<?php

namespace App\Console\Commands;

use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\TournamentGame;
use Illuminate\Console\Command;

/**
 * Clears two-player games that were created, shared and never joined. They sit in Setup forever otherwise, cluttering
 * the creator's game list. Deleted the same way the creator's own "Delete" does it (soft delete); games a tournament
 * tracks are left alone.
 */
class PruneStaleGameLobbies extends Command
{
    protected $signature = 'games:prune-stale-lobbies {--days=7 : Days a lobby may wait for an opponent}';

    protected $description = 'Delete two-player games nobody joined within a week';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $removed = 0;

        Game::query()
            ->where('status', GameStatusEnum::Setup)
            ->where('is_solo', false)
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('players', fn ($q) => $q->where('slot', 2))
            ->whereNotIn('id', TournamentGame::query()->whereNotNull('game_id')->select('game_id'))
            ->each(function (Game $game) use (&$removed) {
                $game->delete();
                $removed++;
            });

        $this->info("Removed {$removed} stale game lobb".($removed === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
