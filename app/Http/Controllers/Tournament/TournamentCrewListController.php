<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentCrewListRequest;
use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\TournamentPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * A registered player attaches (or clears) one of their own Crew Builder lists for the organizers to see. Open while
 * registration is, locked once the tournament starts. Individual events with faction-declaring rulesets only: teams
 * and Garrison-based games have their own flows.
 */
class TournamentCrewListController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function update(TournamentCrewListRequest $request, Tournament $tournament): RedirectResponse
    {
        $userId = (int) Auth::id();
        $player = TournamentPlayer::where('tournament_id', $tournament->id)->forUser($userId)->first();
        abort_if($player === null, 403, 'You are not a player in this tournament.');

        if ($tournament->isTeamEvent() || $tournament->rulesetDefinition()->declaration !== 'faction') {
            return back()->withErrors(['crew' => 'Crew lists are not used for this kind of event.']);
        }

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! in_array($status, [TournamentStatusEnum::Draft, TournamentStatusEnum::Registration], true)) {
            return back()->withErrors(['crew' => 'Lists are locked once the tournament has started. Ask the organizer for a change.']);
        }

        $buildId = $request->validated()['crew_build_id'];
        if ($buildId === null) {
            $player->update(['crew_build_id' => null]);
            $this->broadcastUpdate($tournament, 'crew_list_changed');

            return back();
        }

        $build = CrewBuild::where('user_id', $userId)->where('is_archived', false)->find($buildId);
        if ($build === null) {
            return back()->withErrors(['crew' => 'Pick one of your own crews.']);
        }

        $declared = $player->getRawOriginal('faction');
        if ($declared !== null && $declared !== $build->faction->value) {
            return back()->withErrors(['crew' => "That crew is {$build->faction->label()}, but you registered as a different faction."]);
        }

        // A player who hadn't picked a faction yet takes the crew's.
        $player->update(['crew_build_id' => $build->id, 'faction' => $declared ?? $build->faction->value]);

        $this->broadcastUpdate($tournament, 'crew_list_changed');

        return back();
    }
}
