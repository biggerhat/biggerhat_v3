<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TournamentRsvpController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Tournament $tournament): JsonResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRsvp()) {
            return response()->json(['error' => 'RSVP is not open for this tournament'], 422);
        }

        $userId = Auth::id();
        if (! $userId) {
            abort(401);
        }

        if ($tournament->rsvps()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'Already RSVPed'], 422);
        }

        TournamentRsvp::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
        ]);

        $this->broadcastUpdate($tournament, 'rsvp_added');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament): JsonResponse
    {
        // Users can always withdraw their own RSVP, regardless of tournament phase.
        // This avoids the race where a TO advances to Active before a user cancels.
        $tournament->rsvps()->where('user_id', Auth::id())->delete();

        $this->broadcastUpdate($tournament, 'rsvp_cancelled');

        return response()->json(['success' => true]);
    }
}
