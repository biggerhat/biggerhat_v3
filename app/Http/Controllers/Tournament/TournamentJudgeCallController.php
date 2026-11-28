<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentRoundStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\User;
use App\Notifications\Tournament\TournamentJudgeCalled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * "Call a judge": a player in a game in progress asks the organizers and scorekeepers to come to their table.
 * One call per table every few minutes, so a table cannot flood the desk.
 */
class TournamentJudgeCallController extends Controller
{
    private const COOLDOWN_SECONDS = 180;

    public function store(Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $round = $game->round;
        abort_unless($round->tournament_id === $tournament->id, 404);

        $me = TournamentPlayer::where('tournament_id', $tournament->id)->forUser((int) Auth::id())->first();
        abort_unless($me !== null && in_array($me->id, [$game->player_one_id, $game->player_two_id], true), 403, 'You are not in this game.');

        if ($round->status !== TournamentRoundStatusEnum::InProgress || $game->is_bye) {
            return back()->withErrors(['judge' => 'There is no game in progress to call a judge to.']);
        }

        // Cache::add is atomic: only the first call in the window gets through.
        if (! Cache::add("tournament-judge:{$game->id}", true, self::COOLDOWN_SECONDS)) {
            return back()->withErrors(['judge' => 'A judge was already called for this table a moment ago.']);
        }

        $recipientIds = $tournament->organizers()->pluck('users.id')
            ->merge($tournament->scorers()->pluck('users.id'))
            ->push($tournament->creator_id)
            ->unique();
        foreach (User::whereIn('id', $recipientIds)->get() as $recipient) {
            $recipient->notify(new TournamentJudgeCalled($tournament, $me->display_name, $game->table_number, $round->round_number));
        }

        return back();
    }
}
