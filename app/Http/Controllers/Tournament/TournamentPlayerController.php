<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Concerns\ResolvesMeta;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentPlayerController extends Controller
{
    use BroadcastsTournamentUpdates;
    use ResolvesMeta;

    public function store(Request $request, Tournament $tournament): JsonResponse
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
            ->where('status', \App\Enums\TournamentRoundStatusEnum::InProgress)
            ->exists()
        ) {
            return response()->json([
                'error' => 'Cannot add a player while a round is in progress — wait for the round to finish.',
            ], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'faction' => ['required', 'string'],
            'is_ringer' => ['sometimes', 'boolean'],
            'meta_id' => ['nullable', 'integer', 'exists:metas,id'],
            // Convenience: lets the registration form create a new meta inline.
            'meta_name' => ['nullable', 'string', 'max:100'],
        ]);

        $this->resolveMetaFromName($validated);

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
            'is_ringer' => $validated['is_ringer'] ?? false,
        ]);

        $this->broadcastUpdate($tournament, 'player_added');

        return response()->json(['success' => true, 'player' => $player]);
    }

    public function update(Request $request, Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'faction' => ['nullable', 'string'],
            'is_ringer' => ['sometimes', 'boolean'],
            'is_disqualified' => ['sometimes', 'boolean'],
            'dropped_after_round' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'meta_id' => ['sometimes', 'nullable', 'integer', 'exists:metas,id'],
            'meta_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $this->resolveMetaFromName($validated);

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
}
