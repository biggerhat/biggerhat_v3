<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\FactionEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentRsvp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * RSVP actions live on the public View page, so we redirect back (Inertia-
 * native) instead of returning JSON — the caller uses `router.post/delete`
 * with `preserveScroll` and the resulting redirect transparently refreshes
 * props.
 */
class TournamentRsvpController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Request $request, Tournament $tournament): RedirectResponse
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

        // Faction is optional — RSVPers may not have decided yet, and the TO
        // can still finalize without it. Validating against FactionEnum keeps
        // the column safe for casting on read.
        $validated = $request->validate([
            'faction' => ['nullable', 'string', Rule::in(array_column(FactionEnum::cases(), 'value'))],
        ]);

        TournamentRsvp::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
            'faction' => $validated['faction'] ?? null,
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
