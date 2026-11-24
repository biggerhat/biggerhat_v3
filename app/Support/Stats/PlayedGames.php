<?php

namespace App\Support\Stats;

use App\Enums\GameOriginEnum;
use App\Enums\GameStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Loads every finished game a person played, from the Game Tracker, logged by hand and (when asked) tournament rounds, as {@see PlayedGame}s
 * newest first. What a viewer may see: the person themselves sees everything; anyone else sees tracked games as before, logged games only
 * when their owner made them public, and tournament rounds of completed events.
 */
final class PlayedGames
{
    /**
     * The tracker and logged games: the player rows with their game, for code that needs the models themselves.
     *
     * @return Collection<int, GamePlayer>
     */
    public function records(User $user, bool $viewerIsOwner): Collection
    {
        return GamePlayer::query()
            ->where('user_id', $user->id)
            ->whereHas('game', fn ($q) => $q->where('status', GameStatusEnum::Completed)
                ->when(! $viewerIsOwner, fn ($q) => $q->where(fn ($w) => $w->where('origin', '!=', GameOriginEnum::Logged->value)->orWhere('is_public', true))))
            ->with(['game.strategy:id,name', 'game.players.user:id,name,slug', 'game.players.linkedUser:id,name,slug'])
            ->get();
    }

    /** @return Collection<int, PlayedGame> newest first */
    public function for(User $user, bool $viewerIsOwner, bool $withTournaments = true): Collection
    {
        $records = $this->records($user, $viewerIsOwner);
        $games = $this->fromRecords($user, $records);

        if ($withTournaments) {
            $games = $games->concat($this->fromTournaments($user, $records->pluck('game_id')->all()));
        }

        return $games->sortByDesc(fn (PlayedGame $g) => $g->date.$g->key)->values();
    }

    /**
     * @param  Collection<int, GamePlayer>  $records
     * @return Collection<int, PlayedGame>
     */
    public function fromRecords(User $user, Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $gameIds = $records->pluck('game_id')->all();
        $turns = GameTurn::query()->whereIn('game_id', $gameIds)->with('scheme:id,name')->get()->groupBy('game_id');
        $crews = GameCrewMember::query()->whereIn('game_player_id', $records->pluck('id'))->orderBy('sort_order')->get(['game_player_id', 'display_name', 'is_killed', 'cost'])->groupBy('game_player_id');

        return $records->map(function (GamePlayer $mine) use ($user, $turns, $crews): PlayedGame {
            /** @var Game $game */
            $game = $mine->game;
            $theirs = $game->players->first(fn (GamePlayer $p) => $p->id !== $mine->id);
            $gameTurns = $turns->get($game->id, collect());
            $mineTurns = $gameTurns->where('game_player_id', $mine->id)->keyBy('turn_number');
            $theirTurns = $theirs ? $gameTurns->where('game_player_id', $theirs->id)->keyBy('turn_number') : collect();

            return new PlayedGame(
                source: $game->origin === GameOriginEnum::Logged ? PlayedGame::LOGGED : PlayedGame::TRACKED,
                key: 'g'.$game->id,
                url: $game->origin === GameOriginEnum::Logged ? route('games.logged.show', $game, false) : route('games.summary', $game, false),
                date: ($game->completed_at ?? $game->created_at)->toDateString(),
                result: $this->resultFor($game, $mine, $user),
                isSolo: (bool) $game->is_solo,
                faction: $mine->getRawOriginal('faction'),
                master: $mine->master_name,
                role: $mine->role?->value,
                vp: (int) $mine->total_points,
                oppName: $this->opponentName($game, $theirs),
                oppSlug: $theirs?->user?->slug,
                oppFaction: $theirs?->getRawOriginal('faction'),
                oppMaster: $theirs?->master_name,
                oppVp: (int) ($theirs?->total_points ?? 0),
                strategy: $game->strategy?->name,
                deployment: $game->deployment?->label(),
                encounterSize: $game->encounter_size,
                season: $game->season->label(),
                playMode: $game->play_mode?->label(),
                venue: $game->venue,
                format: $game->format->label(),
                turns: $mineTurns->keys()->merge($theirTurns->keys())->unique()->sort()->values()->map(fn ($n) => [
                    'turn' => (int) $n,
                    'strategy' => (int) ($mineTurns->get($n)?->strategy_points ?? 0),
                    'scheme' => (int) ($mineTurns->get($n)?->scheme_points ?? 0),
                    'scheme_id' => $mineTurns->get($n)?->scheme_id,
                    'scheme_name' => $mineTurns->get($n)?->scheme?->name,
                    'action' => $mineTurns->get($n)?->scheme_action,
                    'opp_strategy' => (int) ($theirTurns->get($n)?->strategy_points ?? 0),
                    'opp_scheme' => (int) ($theirTurns->get($n)?->scheme_points ?? 0),
                ])->all(),
                models: $crews->get($mine->id, collect())->map(fn ($m) => ['name' => $m->display_name, 'killed' => (bool) $m->is_killed, 'cost' => $m->cost])->all(),
                strategyVp: $mineTurns->isEmpty() ? null : (int) $mineTurns->sum('strategy_points'),
                schemeVp: $mineTurns->isEmpty() ? null : (int) $mineTurns->sum('scheme_points'),
            );
        })->values();
    }

    /**
     * Rounds of completed Malifaux tournaments the person played. A round that was played through the tracker is the tracker's game, not counted twice.
     *
     * @param  list<int>  $trackerGameIds
     * @return Collection<int, PlayedGame>
     */
    public function fromTournaments(User $user, array $trackerGameIds): Collection
    {
        $entries = TournamentPlayer::query()
            ->forUser($user->id)
            ->whereHas('tournament', fn ($q) => $q->where('status', TournamentStatusEnum::Completed))
            ->with(['tournament', 'tournament.rounds.strategy:id,name', 'tournament.rounds.games.playerOne.user:id,name,slug', 'tournament.rounds.games.playerTwo.user:id,name,slug'])
            ->get();

        $out = collect();
        foreach ($entries as $entry) {
            $tournament = $entry->tournament;
            if ($tournament->rulesetDefinition()->gameSystem !== 'malifaux') {
                continue;
            }

            foreach ($tournament->rounds as $round) {
                foreach ($round->games as $tg) {
                    /** @var TournamentGame $tg */
                    $result = $this->tournamentResult($tg, $entry->id);
                    if ($result === null || ($tg->game_id !== null && in_array($tg->game_id, $trackerGameIds, true))) {
                        continue;
                    }

                    $first = $tg->player_one_id === $entry->id;
                    $opponent = $first ? $tg->playerTwo : $tg->playerOne;
                    $p = $first ? 'player_one' : 'player_two';
                    $o = $first ? 'player_two' : 'player_one';

                    $out->push(new PlayedGame(
                        source: PlayedGame::TOURNAMENT,
                        key: 't'.$tg->id,
                        url: '/tournaments/'.$tournament->uuid,
                        date: $tournament->event_date->toDateString(),
                        result: $result,
                        isSolo: false,
                        faction: $tg->{$p.'_faction'},
                        master: $tg->{$p.'_master'},
                        role: null,
                        vp: (int) $tg->{$p.'_vp'},
                        oppName: $opponent ? (string) data_get($opponent, 'user.name', $opponent->display_name) : null,
                        oppSlug: data_get($opponent, 'user.slug'),
                        oppFaction: $tg->{$o.'_faction'},
                        oppMaster: $tg->{$o.'_master'},
                        oppVp: (int) $tg->{$o.'_vp'},
                        strategy: $round->strategy?->name,
                        deployment: $round->deployment?->label(),
                        encounterSize: $tournament->encounter_size,
                        season: $tournament->season?->label(),
                        playMode: null,
                        venue: $tournament->name,
                        format: null,
                        strategyVp: isset(((array) $tg->score_breakdown)[$p]['strategy']) ? (int) ((array) $tg->score_breakdown)[$p]['strategy'] : null,
                        schemeVp: isset(((array) $tg->score_breakdown)[$p]['scheme']) ? (int) ((array) $tg->score_breakdown)[$p]['scheme'] : null,
                    ));
                }
            }
        }

        return $out;
    }

    private function resultFor(Game $game, GamePlayer $mine, User $user): string
    {
        if ($game->is_tie) {
            return 'tie';
        }
        if ($game->winner_slot !== null) {
            return $game->winner_slot === $mine->slot ? 'win' : 'loss';
        }

        return $game->winner_id === $user->id ? 'win' : 'loss';
    }

    private function opponentName(Game $game, ?GamePlayer $theirs): ?string
    {
        if ($theirs === null) {
            return null;
        }
        if ($game->is_solo) {
            return $theirs->opponent_name ?: 'Playing both sides';
        }

        return $theirs->user?->name ?? $theirs->opponent_name ?: $theirs->linkedUser?->name;
    }

    private function tournamentResult(TournamentGame $game, int $playerId): ?string
    {
        if ($game->is_bye || ! in_array($playerId, [$game->player_one_id, $game->player_two_id], true)) {
            return null;
        }
        if (! in_array($game->result->value ?? $game->result, ['completed', 'agreed', 'forfeited'], true)) {
            return null;
        }
        if ($game->is_forfeit) {
            return (int) $game->forfeit_player_id === $playerId ? 'loss' : 'win';
        }

        $mine = $game->player_one_id === $playerId ? $game->player_one_vp : $game->player_two_vp;
        $theirs = $game->player_one_id === $playerId ? $game->player_two_vp : $game->player_one_vp;

        return $mine === $theirs ? 'tie' : ($mine > $theirs ? 'win' : 'loss');
    }
}
