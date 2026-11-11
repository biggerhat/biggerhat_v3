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
use App\Models\User;
use App\Notifications\Tournament\TournamentPlayerRegistered;
use Illuminate\Http\JsonResponse;

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

        $validated = $request->validated();

        $this->resolveMetaFromName($validated);

        if ($error = $this->resolveGarrison($validated, $validated['user_id'] ?? null)) {
            return $error;
        }

        if (! empty($validated['is_ringer']) && $tournament->players()->where('is_ringer', true)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        if (! empty($validated['user_id'])
            && $tournament->players()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json(['error' => 'That user is already linked to another player in this tournament'], 422);
        }

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

        if ($player->user_id) {
            User::find($player->user_id)?->notify(new TournamentPlayerRegistered($player));
        }

        $this->broadcastUpdate($tournament, 'player_added');

        return response()->json(['success' => true, 'player' => $player]);
    }

    public function update(TournamentPlayerUpdateRequest $request, Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();

        $this->resolveMetaFromName($validated);

        if ($error = $this->resolveGarrison($validated, $validated['user_id'] ?? $player->user_id, $player->allegiance_id)) {
            return $error;
        }

        if (array_key_exists('user_id', $validated) && $validated['user_id'] !== null) {
            $conflict = $tournament->players()
                ->where('user_id', $validated['user_id'])
                ->where('id', '!=', $player->id)
                ->exists();
            if ($conflict) {
                return response()->json(['error' => 'That user is already linked to another player in this tournament'], 422);
            }
        }

        if (! empty($validated['is_ringer']) && ! $player->is_ringer
            && $tournament->players()->where('is_ringer', true)->where('id', '!=', $player->id)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        if (isset($validated['is_disqualified']) && $validated['is_disqualified'] && ! $player->is_disqualified) {
            $validated['disqualified_at'] = now();
        }

        $player->update($validated);

        $this->broadcastUpdate($tournament, 'player_updated');

        return response()->json(['success' => true]);
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

        $player->delete();

        $this->broadcastUpdate($tournament, 'player_removed');

        return response()->json(['success' => true]);
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
