<?php

namespace App\Support\Game;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\TournamentGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The tournament rounds a person can write a battle report about: a round of a finished Malifaux event they played that was not
 * tracked in the Game Tracker (a tracked one is written about as the game, which is richer).
 */
class LinkableRounds
{
    /** @return Builder<TournamentGame> */
    public static function query(User $user): Builder
    {
        // The competitor is the person's own entry, or a team they are a member of.
        $mine = fn ($p) => $p->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id)));

        return TournamentGame::query()
            ->where('is_bye', false)
            ->whereNull('game_id')
            ->whereIn('result', [TournamentGameResultEnum::Completed->value, TournamentGameResultEnum::Agreed->value, TournamentGameResultEnum::Forfeited->value])
            ->whereHas('round.tournament', fn ($q) => $q->where('status', TournamentStatusEnum::Completed))
            ->where(fn ($q) => $q->whereHas('playerOne', $mine)->orWhereHas('playerTwo', $mine));
    }

    /** The relations a card reads. */
    public const WITH = ['round.tournament', 'round.strategy:id,name', 'playerOne.user:id,name', 'playerTwo.user:id,name'];

    /** @return Collection<int, TournamentGame> newest event first */
    public static function for(User $user, int $limit = 60): Collection
    {
        return self::query($user)->with(self::WITH)->latest('id')->limit($limit)->get()
            ->filter(fn (TournamentGame $tg) => $tg->round->tournament->rulesetDefinition()->gameSystem === 'malifaux')
            ->values();
    }

    public static function find(User $user, int $id): ?TournamentGame
    {
        $tg = self::query($user)->with(self::WITH)->whereKey($id)->first();

        return $tg && $tg->round->tournament->rulesetDefinition()->gameSystem === 'malifaux' ? $tg : null;
    }
}
