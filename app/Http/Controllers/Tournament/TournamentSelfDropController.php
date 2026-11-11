<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use App\Models\User;
use App\Notifications\Tournament\TournamentPlayerWithdrew;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A linked player withdraws themselves from the event. Before it starts that removes their seat (and RSVP), so the next
 * waitlisted RSVP moves up; once it is running they are dropped after the round they are in (or the last finished one),
 * exactly as an organizer's drop would. Team events stay with the organizer.
 */
class TournamentSelfDropController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function store(Tournament $tournament): RedirectResponse
    {
        $userId = (int) Auth::id();
        $player = TournamentPlayer::where('tournament_id', $tournament->id)->forUser($userId)->first();
        abort_if($player === null, 403, 'You are not a player in this tournament.');

        if ($tournament->isTeamEvent()) {
            return back()->withErrors(['withdraw' => 'Ask your organizer to withdraw your team.']);
        }

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if ($status === TournamentStatusEnum::Completed) {
            return back()->withErrors(['withdraw' => 'This tournament is already finished.']);
        }
        if ($player->is_disqualified || $player->dropped_after_round !== null) {
            return back()->withErrors(['withdraw' => 'You have already withdrawn.']);
        }

        $stillPaired = false;
        DB::transaction(function () use ($tournament, $player, $status, $userId, &$stillPaired): void {
            if ($status !== TournamentStatusEnum::Active) {
                $tournament->rsvps()->where('user_id', $userId)->delete();
                $player->delete();

                return;
            }

            $inProgress = $tournament->rounds()
                ->where('status', TournamentRoundStatusEnum::InProgress)
                ->whereHas('games', fn ($q) => $q->where(fn ($w) => $w->where('player_one_id', $player->id)->orWhere('player_two_id', $player->id)))
                ->value('round_number');
            $stillPaired = $inProgress !== null;

            $lastCompleted = (int) $tournament->rounds()->where('status', TournamentRoundStatusEnum::Completed)->max('round_number');
            $player->update(['dropped_after_round' => $inProgress ?? $lastCompleted]);
        });

        $organizerIds = $tournament->organizers()->pluck('users.id')->push($tournament->creator_id)->unique();
        foreach (User::whereIn('id', $organizerIds)->get() as $organizer) {
            $organizer->notify(new TournamentPlayerWithdrew($tournament, $player->display_name, $stillPaired));
        }

        $this->broadcastUpdate($tournament, 'player_withdrew');

        return back();
    }
}
