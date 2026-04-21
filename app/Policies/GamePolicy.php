<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\User;

class GamePolicy
{
    /** Creator or participant can view. */
    public function view(User $user, Game $game): bool
    {
        return $game->creator_id === $user->id
            || $game->players()->where('user_id', $user->id)->exists();
    }

    /** Creator-only actions: settings, observable toggle, solo slot admin. */
    public function update(User $user, Game $game): bool
    {
        return $game->creator_id === $user->id;
    }

    /** Scenario edits are creator-only AND only allowed pre-gameplay. */
    public function updateScenario(User $user, Game $game): bool
    {
        return $this->update($user, $game) && $game->status->isSetupPhase();
    }

    /**
     * Crew member mutations. In solo mode the creator can act on either slot's
     * crew; in a duel the acting user must own the crew member's GamePlayer.
     */
    public function updateCrewMember(User $user, Game $game, GameCrewMember $member): bool
    {
        if ($member->game_id !== $game->id) {
            return false;
        }
        if ($game->is_solo) {
            return $game->creator_id === $user->id;
        }
        /** @var GamePlayer|null $player */
        $player = $game->players()->where('user_id', $user->id)->first();

        return $player !== null && $member->game_player_id === $player->id;
    }
}
