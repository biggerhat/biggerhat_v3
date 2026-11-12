<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\FactionEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentRsvpStoreRequest;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRsvp;
use App\Tournaments\InstantSignup;
use App\Tournaments\WaitlistPromotions;
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

    public function store(TournamentRsvpStoreRequest $request, Tournament $tournament): RedirectResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRsvp()) {
            return back()->withErrors(['rsvp' => 'RSVP is not open for this tournament']);
        }

        if ($tournament->registration_closed) {
            return back()->withErrors(['rsvp' => 'Registration for this tournament has closed']);
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
        $validated = $request->validated();

        $rsvp = TournamentRsvp::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
            'faction' => $validated['faction'] ?? null,
            'allegiance_id' => $validated['allegiance_id'] ?? null,
        ]);

        $seated = InstantSignup::seat($tournament, $rsvp->load('user'));
        $this->broadcastUpdate($tournament, $seated ? 'player_added' : 'rsvp_added');

        return back();
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        // Users can always withdraw their own RSVP, regardless of tournament phase.
        // This avoids the race where a TO advances to Active before a user cancels.
        $userId = Auth::id();

        // Instant sign-up made them a player: cancelling takes them off the list again, unless they've already been paired (then they withdraw instead).
        /** @var TournamentPlayer|null $player */
        $player = $tournament->instant_signup ? $tournament->players()->where('user_id', $userId)->first() : null;
        if ($player !== null) {
            $paired = TournamentGame::where('player_one_id', $player->id)->orWhere('player_two_id', $player->id)->exists();
            if ($paired) {
                return back()->withErrors(['rsvp' => 'You are already in the pairings — use Withdraw instead']);
            }
        }

        $waiting = WaitlistPromotions::snapshot($tournament);
        $tournament->rsvps()->where('user_id', $userId)->delete();
        $player?->delete();
        WaitlistPromotions::notify($tournament, $waiting);

        $this->broadcastUpdate($tournament, 'rsvp_cancelled');

        return back();
    }
}
