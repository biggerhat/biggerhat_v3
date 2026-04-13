<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentOrganizerController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Request $request, Tournament $tournament): JsonResponse
    {
        // Granting management access is creator-only — symmetric with destroy.
        $this->authorize('addOrganizer', $tournament);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if ($tournament->organizers()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json(['error' => 'Already an organizer'], 422);
        }

        $tournament->organizers()->attach($validated['user_id']);

        $this->broadcastUpdate($tournament, 'organizer_added');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament, int $userId): JsonResponse
    {
        $this->authorize('removeOrganizer', $tournament);

        if ($userId === $tournament->creator_id) {
            return response()->json(['error' => 'Cannot remove the creator'], 422);
        }

        $tournament->organizers()->detach($userId);

        $this->broadcastUpdate($tournament, 'organizer_removed');

        return response()->json(['success' => true]);
    }
}
