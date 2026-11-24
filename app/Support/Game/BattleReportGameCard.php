<?php

namespace App\Support\Game;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;

/**
 * The few facts about a game that sit above a battle report: who played, what, and the score. Tracker games and logged ones
 * look the same here. Expects `players.user`, `strategy` and `creator` to be loaded.
 */
class BattleReportGameCard
{
    /** @return array<string, mixed> */
    public static function for(Game $game): array
    {
        $winnerSlot = $game->winner_slot ?? $game->players->firstWhere(fn (GamePlayer $p) => $game->winner_id !== null && $p->user_id === $game->winner_id)?->slot;

        return [
            'uuid' => $game->uuid,
            'origin' => $game->isLogged() ? 'logged' : 'tracked',
            'name' => $game->name,
            'played_on' => ($game->completed_at ?? $game->created_at)?->toDateString(),
            'encounter_size' => $game->encounter_size,
            'strategy' => $game->strategy?->name,
            'is_tie' => (bool) $game->is_tie,
            'winner_slot' => $winnerSlot,
            // The game's own page, only when the reader may open it (a tracker summary is open to all; a logged game only when public).
            'url' => $game->isLogged()
                ? ($game->is_public ? route('games.logged.show', $game->uuid) : null)
                : route('games.summary', $game->uuid),
            'players' => $game->players->sortBy('slot')->map(fn (GamePlayer $p) => [
                'slot' => $p->slot,
                'name' => data_get($p, 'user.name') ?? $p->opponent_name ?? 'Opponent',
                'faction' => $p->faction?->value,
                'master' => $p->master_name,
                'points' => (int) $p->total_points,
            ])->values()->all(),
        ];
    }

    /**
     * The same card for a tournament round that was not tracked in the Game Tracker. Expects LinkableRounds::WITH loaded.
     *
     * @return array<string, mixed>
     */
    public static function forRound(TournamentGame $tg): array
    {
        $tournament = $tg->round->tournament;
        $one = (int) $tg->player_one_vp;
        $two = (int) $tg->player_two_vp;
        $winner = $tg->is_forfeit
            ? ((int) $tg->forfeit_player_id === $tg->player_one_id ? 2 : 1)
            : ($one === $two ? null : ($one > $two ? 1 : 2));
        $side = fn (string $p, int $slot, ?TournamentPlayer $player) => [
            'slot' => $slot,
            'name' => (string) (data_get($player, 'user.name') ?? $player->display_name ?? 'Player'),
            'faction' => $tg->{$p.'_faction'} instanceof \BackedEnum ? $tg->{$p.'_faction'}->value : $tg->{$p.'_faction'},
            'master' => $tg->{$p.'_master'},
            'points' => (int) $tg->{$p.'_vp'},
        ];

        return [
            'uuid' => 'round-'.$tg->id,
            'origin' => 'tournament',
            'name' => $tournament->name.' · Round '.$tg->round->round_number,
            'played_on' => $tournament->event_date->toDateString(),
            'encounter_size' => $tournament->encounter_size,
            'strategy' => $tg->round->strategy?->name,
            'is_tie' => $winner === null,
            'winner_slot' => $winner,
            'url' => '/tournaments/'.$tournament->uuid,
            'players' => [$side('player_one', 1, $tg->playerOne), $side('player_two', 2, $tg->playerTwo)],
        ];
    }

    /** The relations the card reads. */
    public const WITH = ['players.user:id,name', 'strategy:id,name'];
}
