<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentPlayerController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRegistration() && ! $status->isEditable()) {
            return response()->json(['error' => 'Cannot add players in this tournament state'], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'faction' => ['required', 'string'],
            'is_ringer' => ['sometimes', 'boolean'],
        ]);

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
        ]);

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

        $player->delete();

        $this->broadcastUpdate($tournament, 'player_removed');

        return response()->json(['success' => true]);
    }
}
