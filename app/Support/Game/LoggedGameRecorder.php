<?php

namespace App\Support\Game;

use App\Enums\GameFormatEnum;
use App\Enums\GameOriginEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GamePlayer;
use App\Models\GameTurn;
use App\Models\User;
use App\Notifications\Game\LoggedGameLinkRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes a game that was played and tracked somewhere else, from the validated form (App\Http\Requests\Games\Logged). It makes the
 * same rows the Game Tracker makes (a game, two players, a turn per player per turn, crew members) so every screen and every stat
 * that reads games reads it too, and nothing in it can be told apart from a tracked game except `origin`.
 *
 * The person logging the game is slot 1 (`me`); the opponent is slot 2 and has no account unless the logger linked one and that
 * person confirmed (App\Http\Controllers\Game\LoggedGameController::confirmLink), at which point their user id is set on the row.
 */
final class LoggedGameRecorder
{
    /** @param  array<string, mixed>  $data  the validated request */
    public function create(User $me, array $data): Game
    {
        return DB::transaction(function () use ($me, $data): Game {
            $game = Game::create([
                'creator_id' => $me->id,
                'origin' => GameOriginEnum::Logged,
                'status' => GameStatusEnum::Completed,
                'is_solo' => false,
                ...$this->gameFields($data, $me->id),
            ]);

            $mine = GamePlayer::create(['game_id' => $game->id, 'slot' => 1, 'user_id' => $me->id, ...$this->playerFields($data['me'])]);
            $theirs = GamePlayer::create(['game_id' => $game->id, 'slot' => 2, 'user_id' => null, ...$this->playerFields($data['opponent']), ...$this->opponentFields($data['opponent'], null)]);

            $this->writeDetail($game, [$mine, $theirs], $data);
            $this->notifyLink($game, $theirs);

            return $game;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Game $game, array $data): Game
    {
        return DB::transaction(function () use ($game, $data): Game {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $locked->update($this->gameFields($data, $locked->creator_id));

            $players = $locked->players()->get();
            /** @var GamePlayer $mine */
            $mine = $players->firstWhere('slot', 1);
            /** @var GamePlayer $theirs */
            $theirs = $players->firstWhere('slot', 2);

            $mine->update($this->playerFields($data['me']));
            $previousLink = $theirs->linked_user_id;
            $theirs->update([...$this->playerFields($data['opponent']), ...$this->opponentFields($data['opponent'], $theirs)]);

            // a different person invited undoes the old confirmation: the row stops being theirs until the new person confirms
            $theirs->refresh();
            if ($previousLink !== $theirs->linked_user_id) {
                $theirs->update(['user_id' => null]);
            }

            $locked->turns()->delete();
            $locked->crewMembers()->delete();
            $this->writeDetail($locked, [$mine->refresh(), $theirs->refresh()], $data);

            if ($previousLink !== $theirs->linked_user_id) {
                $this->notifyLink($locked, $theirs);
            }

            return $locked;
        });
    }

    /**
     * The fields on the game row.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function gameFields(array $data, int $creatorId): array
    {
        $format = GameFormatEnum::from($data['format']);
        $playedAt = Carbon::parse($data['played_on'])->setTime(12, 0);
        $turns = collect($data['turns'] ?? []);
        $result = $data['result'];

        return [
            'format' => $format,
            'name' => $data['name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'venue' => $data['venue'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'play_mode' => $data['play_mode'] ?? null,
            'season' => $data['season'] ?? PoolSeasonEnum::defaultSeason()->value,
            'encounter_size' => $data['encounter_size'],
            // a format with no scenario (Bonanza Brawl) has none of these
            'strategy_id' => $format->usesScenario() ? ($data['strategy_id'] ?? null) : null,
            'deployment' => $format->usesScenario() ? ($data['deployment'] ?? null) : null,
            'scheme_pool' => $format->usesScenario() ? array_values($data['scheme_pool'] ?? []) : [],
            'max_turns' => max(5, (int) $turns->max('turn_number')),
            'current_turn' => max(5, (int) $turns->max('turn_number')),
            'is_tie' => $result === 'tie',
            // the person logging it is slot 1; a win by the opponent has no account to point at, so it is the slot that says it
            'winner_id' => $result === 'win' ? $creatorId : null,
            'winner_slot' => $result === 'tie' ? null : ($result === 'win' ? 1 : 2),
            'started_at' => $playedAt,
            'completed_at' => $playedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    private function playerFields(array $side): array
    {
        $master = ! empty($side['master_id']) ? Character::find($side['master_id']) : null;

        return [
            'faction' => $side['faction'],
            'master_id' => $master?->id,
            'master_name' => $master?->display_name ?? ($side['master_name'] ?? null),
            'crew_build_id' => $side['crew_build_id'] ?? null,
            'role' => $side['role'] ?? null,
            'total_points' => (int) $side['total_points'],
            'soulstone_pool' => (int) ($side['soulstone_pool'] ?? 0),
            'is_turn_complete' => true,
            'is_game_complete' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    private function opponentFields(array $side, ?GamePlayer $existing): array
    {
        $linkedId = $side['linked_user_id'] ?? null;
        $name = $side['opponent_name'] ?? null;
        if ($linkedId && ! $name) {
            $name = User::find($linkedId)?->name;
        }

        // an unchanged link keeps its answer; a new one waits for the person to confirm
        $unchanged = $existing !== null && $linkedId !== null && $existing->linked_user_id === (int) $linkedId;

        return [
            'opponent_name' => $name,
            'linked_user_id' => $linkedId,
            'link_status' => $linkedId ? ($unchanged ? $existing->link_status : 'pending') : null,
            'user_id' => $unchanged && $existing->link_status === 'confirmed' ? $existing->user_id : null,
        ];
    }

    /**
     * The turns and the crews.
     *
     * @param  list<GamePlayer>  $players  slot 1 then slot 2
     * @param  array<string, mixed>  $data
     */
    private function writeDetail(Game $game, array $players, array $data): void
    {
        foreach ($data['turns'] ?? [] as $turn) {
            foreach (['me' => $players[0], 'opponent' => $players[1]] as $who => $player) {
                $row = $turn[$who] ?? [];
                $strategy = (int) ($row['strategy_points'] ?? 0);
                $scheme = (int) ($row['scheme_points'] ?? 0);

                GameTurn::create([
                    'game_id' => $game->id,
                    'game_player_id' => $player->id,
                    'turn_number' => $turn['turn_number'],
                    'scheme_id' => $row['scheme_id'] ?? null,
                    'scheme_action' => $row['scheme_action'] ?? null,
                    'strategy_points' => $strategy,
                    'scheme_points' => $scheme,
                    'points_scored' => $strategy + $scheme,
                    'strategy_bonus_used' => (bool) ($row['strategy_bonus_used'] ?? false),
                    'notes' => $row['notes'] ?? null,
                ]);
            }
        }

        foreach (['me' => $players[0], 'opponent' => $players[1]] as $who => $player) {
            $order = 0;
            foreach ($data[$who]['models'] ?? [] as $model) {
                $character = Character::with('characteristics')->find($model['character_id']);
                if (! $character) {
                    continue;
                }
                $killed = (bool) ($model['killed'] ?? false);

                GameCrewMember::create([
                    'game_id' => $game->id,
                    'game_player_id' => $player->id,
                    'character_id' => $character->id,
                    'display_name' => $character->display_name,
                    'faction' => $character->getRawOriginal('faction'),
                    'current_health' => $killed ? 0 : $character->health,
                    'max_health' => $character->health,
                    'defense' => $character->defense,
                    'willpower' => $character->willpower,
                    'speed' => $character->speed,
                    'size' => $character->size,
                    'characteristics' => $character->characteristics->pluck('name')->values()->all(),
                    'is_killed' => $killed,
                    'cost' => $model['cost'] ?? $character->cost,
                    'station' => $character->station?->value,
                    'hiring_category' => $model['hiring_category'] ?? null,
                    'attached_upgrades' => [],
                    'attached_tokens' => [],
                    'attached_markers' => [],
                    'sort_order' => $order++,
                ]);
            }
        }
    }

    private function notifyLink(Game $game, GamePlayer $opponent): void
    {
        if ($opponent->link_status !== 'pending' || ! $opponent->linked_user_id) {
            return;
        }

        $user = User::find($opponent->linked_user_id);
        if ($user) {
            // the notification channels may be down; the logged game is saved either way
            try {
                $user->notify(new LoggedGameLinkRequested($game));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
