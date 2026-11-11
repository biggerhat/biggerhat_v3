<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * A linked player checks themselves in (or back out) on the public page. Organizers check players in
 * from Manage (TournamentPlayerController). Inertia actions, so — like RSVPs — they redirect back.
 */
class TournamentCheckInController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Tournament $tournament): RedirectResponse
    {
        return $this->setCheckedIn($tournament, true);
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        return $this->setCheckedIn($tournament, false);
    }

    private function setCheckedIn(Tournament $tournament, bool $checkedIn): RedirectResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! in_array($status, [TournamentStatusEnum::Registration, TournamentStatusEnum::Active], true)) {
            return back()->withErrors(['check_in' => 'Check-in isn’t open for this tournament.']);
        }

        $player = TournamentPlayer::where('tournament_id', $tournament->id)->forUser((int) Auth::id())->first();
        abort_if($player === null || $player->is_disqualified, 403, 'You are not a player in this tournament.');

        $player->update(['checked_in_at' => $checkedIn ? ($player->checked_in_at ?? now()) : null]);

        $this->broadcastUpdate($tournament, $checkedIn ? 'player_checked_in' : 'player_checked_out');

        return back();
    }
}
