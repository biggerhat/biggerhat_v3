<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Concerns\ResolvesMeta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentPlayerStoreRequest;
use App\Http\Requests\Tournaments\TournamentPlayerUpdateRequest;
use App\Models\TOS\Garrison;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\TournamentPlayerMember;
use App\Models\User;
use App\Notifications\Tournament\TournamentPlayerRegistered;
use App\Tournaments\WaitlistPromotions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TournamentPlayerController extends Controller
{
    use BroadcastsTournamentUpdates;
    use ResolvesMeta;

    public function store(TournamentPlayerStoreRequest $request, Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        // Allow adds during Draft, Registration, AND Active (between rounds).
        // Adds are only forbidden once the tournament is Completed.
        if ($status === TournamentStatusEnum::Completed) {
            return response()->json(['error' => 'Cannot add players to a completed tournament'], 422);
        }
        // Disallow adding mid-round: the new player would have no game in the
        // round in progress, which would either need a forced bye or look like
        // a missing pairing. Wait until the round ends.
        if ($tournament->rounds()
            ->where('status', TournamentRoundStatusEnum::InProgress)
            ->exists()
        ) {
            return response()->json([
                'error' => 'Cannot add a player while a round is in progress — wait for the round to finish.',
            ], 422);
        }

        // The ringer is a stand-in, not a seat; everyone else counts against the player limit.
        if (empty($request->validated()['is_ringer']) && $tournament->isFull()) {
            return response()->json(['error' => "The tournament is full ({$tournament->max_players}). Raise the player limit in Settings before adding more."], 422);
        }

        $validated = $request->validated();
        $members = $validated['members'] ?? null;
        unset($validated['members']);

        $this->resolveMetaFromName($validated);

        if ($error = $this->resolveGarrison($validated, $validated['user_id'] ?? null)) {
            return $error;
        }

        if (! empty($validated['is_ringer']) && $tournament->players()->where('is_ringer', true)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        if (! empty($validated['user_id']) && $this->userAlreadyCompeting($tournament, (int) $validated['user_id'])) {
            return response()->json(['error' => 'That user is already linked to another player in this tournament'], 422);
        }

        // A failed member sync throws an HttpResponseException, which rolls the whole transaction back.
        $player = DB::transaction(function () use ($tournament, $validated, $members): TournamentPlayer {
            $player = TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'display_name' => $validated['display_name'],
                'user_id' => $validated['user_id'] ?? null,
                'meta_id' => $validated['meta_id'] ?? null,
                'faction' => $validated['faction'] ?? null,
                'allegiance_id' => $validated['allegiance_id'] ?? null,
                'garrison_id' => $validated['garrison_id'] ?? null,
                'is_ringer' => $validated['is_ringer'] ?? false,
            ]);

            if ($members !== null && ($error = $this->syncMembers($tournament, $player, $members)) !== null) {
                throw new HttpResponseException($error);
            }

            return $player;
        });

        foreach ($player->load('members')->linkedUserIds() as $userId) {
            User::find($userId)?->notify(new TournamentPlayerRegistered($player));
        }

        $this->broadcastUpdate($tournament, 'player_added');

        return response()->json(['success' => true, 'player' => $player]);
    }

    public function update(TournamentPlayerUpdateRequest $request, Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();
        $members = $validated['members'] ?? null;
        unset($validated['members']);

        $this->resolveMetaFromName($validated);

        if ($error = $this->resolveGarrison($validated, $validated['user_id'] ?? $player->user_id, $player->allegiance_id)) {
            return $error;
        }

        if (array_key_exists('user_id', $validated) && $validated['user_id'] !== null
            && $this->userAlreadyCompeting($tournament, (int) $validated['user_id'], exceptPlayerId: $player->id)) {
            return response()->json(['error' => 'That user is already linked to another player in this tournament'], 422);
        }

        if (! empty($validated['is_ringer']) && ! $player->is_ringer
            && $tournament->players()->where('is_ringer', true)->where('id', '!=', $player->id)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        if (isset($validated['is_disqualified']) && $validated['is_disqualified'] && ! $player->is_disqualified) {
            $validated['disqualified_at'] = now();
        }

        if (array_key_exists('checked_in', $validated)) {
            $validated['checked_in_at'] = $validated['checked_in'] ? ($player->checked_in_at ?? now()) : null;
            unset($validated['checked_in']);
        }

        DB::transaction(function () use ($tournament, $player, $validated, $members): void {
            $player->update($validated);

            if ($members !== null && ($error = $this->syncMembers($tournament, $player, $members)) !== null) {
                throw new HttpResponseException($error);
            }
        });

        $this->broadcastUpdate($tournament, 'player_updated');

        return response()->json(['success' => true]);
    }

    /** Check in every player who hasn't arrived yet (disqualified players are skipped). */
    public function checkInAll(Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $count = $tournament->players()
            ->whereNull('checked_in_at')
            ->where('is_disqualified', false)
            ->update(['checked_in_at' => now()]);

        $this->broadcastUpdate($tournament, 'players_checked_in');

        return response()->json(['success' => true, 'checked_in' => $count]);
    }

    public function destroy(Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        $this->authorize('manage', $tournament);

        // Refuse to delete a player who has played games. Deletion cascades
        // through the player_one_id FK and would silently destroy game
        // history (which is probably what the TO has reported as
        // "opponents' scores changed"). Guide them to "Drop" instead — it's
        // a non-destructive status that excludes them from future pairings.
        $hasGames = $player->gamesAsPlayerOne()->exists() || $player->gamesAsPlayerTwo()->exists();
        if ($hasGames) {
            return response()->json([
                'error' => 'This player has played games — drop them instead so their match history stays intact.',
            ], 422);
        }

        $waiting = WaitlistPromotions::snapshot($tournament);
        $player->delete();
        WaitlistPromotions::notify($tournament, $waiting);

        $this->broadcastUpdate($tournament, 'player_removed');

        return response()->json(['success' => true]);
    }

    /** Whether an account already plays in this event — as a player, or as a member of any team. */
    private function userAlreadyCompeting(Tournament $tournament, int $userId, ?int $exceptPlayerId = null, ?int $exceptMemberId = null): bool
    {
        return $tournament->players()->where('user_id', $userId)->when($exceptPlayerId, fn ($q) => $q->where('id', '!=', $exceptPlayerId))->exists()
            || TournamentPlayerMember::where('user_id', $userId)
                ->whereHas('team', fn ($q) => $q->where('tournament_id', $tournament->id))
                ->when($exceptMemberId, fn ($q) => $q->where('id', '!=', $exceptMemberId))
                ->exists();
    }

    /**
     * Make a team's members match the submitted list: members with an `id` are updated in place (so an
     * unchanged private Garrison stays linked), new ones are created, and any not submitted are removed.
     * Returns an error response (caller rolls back) or null.
     *
     * @param  list<array<string, mixed>>  $members
     */
    private function syncMembers(Tournament $tournament, TournamentPlayer $team, array $members): ?JsonResponse
    {
        $existing = $team->members()->get()->keyBy('id');
        $keep = [];

        foreach (array_values($members) as $position => $data) {
            $member = isset($data['id']) ? $existing->get($data['id']) : null;
            if (isset($data['id']) && $member === null) {
                return response()->json(['error' => 'One of those team members no longer exists — reload and try again.'], 422);
            }

            $userId = $data['user_id'] ?? null;
            if ($userId !== null && $this->userAlreadyCompeting($tournament, (int) $userId, exceptPlayerId: $team->id, exceptMemberId: $member?->id)) {
                return response()->json(['error' => 'That user is already linked to another player in this tournament'], 422);
            }

            if ($error = $this->resolveGarrison($data, $userId, $data['allegiance_id'] ?? $member?->allegiance_id)) {
                return $error;
            }

            $attributes = [
                'display_name' => $data['display_name'],
                'user_id' => $userId,
                'position' => $position,
            ];
            foreach (['faction', 'allegiance_id', 'garrison_id'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if ($member) {
                $member->update($attributes);
            } else {
                $member = $team->members()->create($attributes);
            }
            $keep[] = $member->id;
        }

        $team->members()->whereNotIn('id', $keep)->delete();

        return null;
    }

    /**
     * Turn a submitted `garrison_share_code` into `garrison_id` (and the Garrison's Allegiance).
     * A Garrison is linkable when it is shared publicly or owned by the linked user, and must
     * belong to the declared Allegiance. Absent key = leave as is; empty value = unlink.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveGarrison(array &$validated, ?int $userId, ?int $currentAllegianceId = null): ?JsonResponse
    {
        if (! array_key_exists('garrison_share_code', $validated)) {
            return null;
        }

        $code = trim((string) $validated['garrison_share_code']);
        unset($validated['garrison_share_code']);

        if ($code === '') {
            $validated['garrison_id'] = null;

            return null;
        }

        $garrison = Garrison::where('share_code', $code)
            ->where(fn ($q) => $q->where('is_public', true)->when($userId, fn ($qq) => $qq->orWhere('user_id', $userId)))
            ->first();

        if (! $garrison) {
            return response()->json(['error' => 'That Garrison code was not found, or the Garrison is not shared publicly.'], 422);
        }

        $declared = $validated['allegiance_id'] ?? $currentAllegianceId;
        if ($declared !== null && (int) $declared !== $garrison->allegiance_id) {
            return response()->json(['error' => 'That Garrison belongs to a different Allegiance than the player declared.'], 422);
        }

        $validated['garrison_id'] = $garrison->id;
        $validated['allegiance_id'] = $garrison->allegiance_id;

        return null;
    }
}
