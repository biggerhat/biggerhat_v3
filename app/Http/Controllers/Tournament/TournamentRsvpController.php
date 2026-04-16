<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * RSVP actions live on the public View page, so we redirect back (Inertia-
 * native) instead of returning JSON — the caller uses `router.post/delete`
 * with `preserveScroll` and the resulting redirect transparently refreshes
 * props.
 */
class TournamentRsvpController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Tournament $tournament): RedirectResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRsvp()) {
            return back()->withErrors(['rsvp' => 'RSVP is not open for this tournament']);
        }

        $userId = Auth::id();
        if (! $userId) {
            abort(401);
        }

        if ($tournament->rsvps()->where('user_id', $userId)->exists()) {
            return back()->withErrors(['rsvp' => 'Already RSVPed']);
        }

        TournamentRsvp::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
        ]);

        $this->broadcastUpdate($tournament, 'rsvp_added');

        return back();
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        // Users can always withdraw their own RSVP, regardless of tournament phase.
        // This avoids the race where a TO advances to Active before a user cancels.
        $tournament->rsvps()->where('user_id', Auth::id())->delete();

        $this->broadcastUpdate($tournament, 'rsvp_cancelled');

        return back();
    }
}
