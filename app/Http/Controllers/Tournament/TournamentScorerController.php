<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentScorerStoreRequest;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\Tournament\TournamentScorerAdded;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Scorekeepers: accounts an organizer lets enter and correct results (from the scoring desk) without handing over the event.
 */
class TournamentScorerController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(TournamentScorerStoreRequest $request, Tournament $tournament): JsonResponse
    {
        $userId = (int) $request->validated()['user_id'];

        if ($tournament->isOrganizer($userId)) {
            return response()->json(['error' => 'They already organize this event.'], 422);
        }
        if ($tournament->isScorer($userId)) {
            return response()->json(['error' => 'Already a scorekeeper.'], 422);
        }

        $tournament->scorers()->attach($userId);
        User::find($userId)?->notify(new TournamentScorerAdded($tournament));

        $this->broadcastUpdate($tournament, 'scorer_added');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament, int $userId): JsonResponse
    {
        Gate::authorize('manage', $tournament);

        $tournament->scorers()->detach($userId);

        $this->broadcastUpdate($tournament, 'scorer_removed');

        return response()->json(['success' => true]);
    }
}
