<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentAwardRequest;
use App\Models\Tournament;
use App\Models\TournamentAward;
use App\Models\TournamentPlayer;
use App\Models\User;
use App\Notifications\Tournament\TournamentAwardGiven;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Side awards: the organizer names them (best painted, sportsmanship, ...) and gives each to a competitor. Whoever
 * receives one is notified; awards with no recipient yet stay organizer-only.
 */
class TournamentAwardController extends Controller
{
    use BroadcastsTournamentUpdates;

    private const MAX_AWARDS = 12;

    public function store(TournamentAwardRequest $request, Tournament $tournament): JsonResponse
    {
        if ($tournament->awards()->count() >= self::MAX_AWARDS) {
            return response()->json(['error' => 'An event can have up to '.self::MAX_AWARDS.' awards.'], 422);
        }

        $validated = $request->validated();
        $award = $tournament->awards()->create($validated + ['sort_order' => (int) $tournament->awards()->max('sort_order') + 1]);

        $this->afterChange($tournament, $award, null);

        return response()->json(['success' => true, 'award' => $award]);
    }

    public function update(TournamentAwardRequest $request, Tournament $tournament, TournamentAward $award): JsonResponse
    {
        $before = $award->player_id;
        $award->update($request->validated());

        $this->afterChange($tournament, $award, $before);

        return response()->json(['success' => true, 'award' => $award]);
    }

    public function destroy(Tournament $tournament, TournamentAward $award): JsonResponse
    {
        Gate::authorize('manage', $tournament);

        $award->delete();
        $this->broadcastUpdate($tournament, 'awards_updated');

        return response()->json(['success' => true]);
    }

    /** Tell the new recipient (not on a no-op edit or a rename), then refresh watchers. */
    private function afterChange(Tournament $tournament, TournamentAward $award, ?int $previousPlayerId): void
    {
        if ($award->player_id !== null && $award->player_id !== $previousPlayerId) {
            $player = TournamentPlayer::with('members')->find($award->player_id);
            if ($player) {
                foreach (User::whereIn('id', $player->linkedUserIds())->get() as $user) {
                    $user->notify(new TournamentAwardGiven($tournament, $award->name));
                }
            }
        }

        $this->broadcastUpdate($tournament, 'awards_updated');
    }
}
