<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentReportStatusEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\DisputeTournamentGameRequest;
use App\Http\Requests\Tournaments\ReportTournamentGameRequest;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Services\TournamentScoreRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Player self-reporting: one player proposes the result of their own game, the opponent confirms
 * (making it official) or disputes (handing it to the organizer). Nothing here counts towards
 * standings until it is confirmed or the organizer enters a score. These are Inertia actions on the
 * public page, so — like RSVPs — they redirect back instead of returning JSON.
 */
class TournamentGameReportController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(private readonly TournamentScoreRecorder $scores) {}

    /** Propose (or replace one's own proposal for) the result. */
    public function store(ReportTournamentGameRequest $request, Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $me = $this->myPlayer($game);
        $validated = $request->validated();

        $error = DB::transaction(function () use ($tournament, $game, $me, $validated): ?string {
            $locked = TournamentGame::lockForUpdate()->findOrFail($game->id);
            if ($error = $this->reportableError($tournament, $locked)) {
                return $error;
            }
            if ($locked->report_status === TournamentReportStatusEnum::Pending && $locked->reported_by_player_id !== $me->id) {
                return 'Your opponent already submitted a result — confirm or dispute it instead.';
            }

            $locked->update([
                'report_status' => TournamentReportStatusEnum::Pending,
                'reported_by_player_id' => $me->id,
                'reported_at' => now(),
                'reported_score' => [
                    'player_one' => $validated['player_one_score'],
                    'player_two' => $validated['player_two_score'],
                    'agreed' => (bool) ($validated['agreed'] ?? false),
                ],
                'report_note' => null,
            ]);

            return null;
        });

        return $this->finish($tournament, $error, 'report_submitted');
    }

    /** The opponent accepts the proposed result, making it official. */
    public function confirm(Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $me = $this->myPlayer($game);

        $error = DB::transaction(function () use ($tournament, $game, $me): ?string {
            $locked = TournamentGame::lockForUpdate()->findOrFail($game->id);
            if ($error = $this->answerableError($tournament, $locked, $me)) {
                return $error;
            }

            $proposal = (array) $locked->reported_score;
            $this->scores->record(
                $locked,
                (array) ($proposal['player_one'] ?? []),
                (array) ($proposal['player_two'] ?? []),
                (bool) ($proposal['agreed'] ?? false),
            );

            return null;
        });

        return $this->finish($tournament, $error, 'score_updated');
    }

    /** The opponent disagrees; the organizer will settle it. */
    public function dispute(DisputeTournamentGameRequest $request, Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $me = $this->myPlayer($game);
        $note = $request->validated('note');

        $error = DB::transaction(function () use ($tournament, $game, $me, $note): ?string {
            $locked = TournamentGame::lockForUpdate()->findOrFail($game->id);
            if ($error = $this->answerableError($tournament, $locked, $me)) {
                return $error;
            }

            $locked->update(['report_status' => TournamentReportStatusEnum::Disputed, 'report_note' => $note]);

            return null;
        });

        return $this->finish($tournament, $error, 'report_disputed');
    }

    /** The reporter takes their proposal back (also clears a dispute so they can start over). */
    public function destroy(Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $me = $this->myPlayer($game);

        $error = DB::transaction(function () use ($game, $me): ?string {
            $locked = TournamentGame::lockForUpdate()->findOrFail($game->id);
            if ($locked->reported_by_player_id !== $me->id || $locked->report_status === null) {
                return 'You have no submitted result to withdraw.';
            }
            if ($locked->round->status !== TournamentRoundStatusEnum::InProgress) {
                return 'Scores are locked after the round is completed.';
            }

            $locked->update($this->scores->clearedReport());

            return null;
        });

        return $this->finish($tournament, $error, 'report_withdrawn');
    }

    /** The signed-in user's seat in this game, or 403. */
    private function myPlayer(TournamentGame $game): TournamentPlayer
    {
        $player = TournamentPlayer::where('user_id', Auth::id())
            ->whereIn('id', array_filter([$game->player_one_id, $game->player_two_id]))
            ->first();

        abort_if($player === null, 403, 'You are not playing in this game.');

        return $player;
    }

    private function reportableError(Tournament $tournament, TournamentGame $game): ?string
    {
        if (! $tournament->allow_self_report) {
            return 'Players can’t submit results for this event — give your scores to the organizer.';
        }
        if ($tournament->status !== TournamentStatusEnum::Active) {
            return 'This tournament isn’t running.';
        }
        if ($game->is_bye || $game->is_forfeit || $game->player_two_id === null) {
            return 'There is no result to report for this game.';
        }
        if ($game->round->status !== TournamentRoundStatusEnum::InProgress) {
            return $game->round->status === TournamentRoundStatusEnum::Completed
                ? 'Scores are locked after the round is completed.'
                : 'The round hasn’t started yet.';
        }
        if ($game->result !== TournamentGameResultEnum::Pending) {
            return 'This game already has a result.';
        }

        return null;
    }

    /** Confirm / dispute: the game must hold an open proposal and the actor must be the other player. */
    private function answerableError(Tournament $tournament, TournamentGame $game, TournamentPlayer $me): ?string
    {
        if ($error = $this->reportableError($tournament, $game)) {
            return $error;
        }
        if ($game->report_status !== TournamentReportStatusEnum::Pending) {
            return 'There is no submitted result waiting for an answer.';
        }
        if ($game->reported_by_player_id === $me->id) {
            return 'Your opponent has to answer your result — you can withdraw it instead.';
        }

        return null;
    }

    private function finish(Tournament $tournament, ?string $error, string $reason): RedirectResponse
    {
        if ($error) {
            return back()->withErrors(['report' => $error]);
        }

        $this->broadcastUpdate($tournament, $reason);

        return back();
    }
}
