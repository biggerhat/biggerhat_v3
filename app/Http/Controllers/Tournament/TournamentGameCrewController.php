<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\FactionEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentGameCrewRequest;
use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * A player attaches (or clears) the Crew Builder crew they are bringing to THIS round's game, so the organizer can see it
 * and the pairing's master / title fill in. Crews change from round to round, so this belongs to the pairing, not the
 * player. Open from pairing until the game has a result. Individual events under a faction-declaring ruleset only.
 */
class TournamentGameCrewController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function update(TournamentGameCrewRequest $request, Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $game->loadMissing('round');
        abort_unless($game->round !== null && $game->round->tournament_id === $tournament->id, 404);

        $userId = (int) Auth::id();
        $side = $this->sideFor($tournament, $game, $userId);
        abort_if($side === null, 403, 'You are not playing in this game.');

        if ($tournament->isTeamEvent() || $tournament->rulesetDefinition()->declaration !== 'faction') {
            return back()->withErrors(['crew' => 'Crew lists are not used for this kind of event.']);
        }
        if ($game->is_bye || $game->player_two_id === null) {
            return back()->withErrors(['crew' => 'A bye has no game to bring a crew to.']);
        }
        if ($game->round->status === TournamentRoundStatusEnum::Completed || $game->result !== TournamentGameResultEnum::Pending) {
            return back()->withErrors(['crew' => 'This game already has a result, so its crew is locked. Ask the organizer for a change.']);
        }

        $buildId = $request->validated()['crew_build_id'];
        if ($buildId === null) {
            $game->update(["player_{$side}_crew_build_id" => null]);
            $this->broadcastUpdate($tournament, 'crew_list_changed');

            return back();
        }

        $build = CrewBuild::where('user_id', $userId)->where('is_archived', false)->with('master:id,name,display_name')->find($buildId);
        if ($build === null) {
            return back()->withErrors(['crew' => 'Pick one of your own crews.']);
        }

        $player = $game->{$side === 'one' ? 'playerOne' : 'playerTwo'};
        $declared = $player?->getRawOriginal('faction') ?? $game->{"player_{$side}_faction"};
        if ($declared !== null && $declared !== $build->faction->value) {
            return back()->withErrors(['crew' => "That crew is {$build->faction->label()}, but you are playing {$this->factionLabel($declared)}."]);
        }

        $leader = $build->master;
        if ($leader !== null && ($reuse = $this->leaderAlreadyPlayed($tournament, $game, $side, $leader->name, $leader->display_name))) {
            return back()->withErrors(['crew' => $reuse]);
        }

        $game->update([
            "player_{$side}_crew_build_id" => $build->id,
            "player_{$side}_faction" => $declared ?? $build->faction->value,
            // The pairing's master and title follow the crew, so the organizer doesn't type them again.
            ...($leader !== null ? ["player_{$side}_master" => $leader->name, "player_{$side}_title" => $leader->display_name] : []),
        ]);
        if ($player !== null && $player->getRawOriginal('faction') === null) {
            TournamentPlayer::whereKey($player->id)->update(['faction' => $build->faction->value]);
        }

        $this->broadcastUpdate($tournament, 'crew_list_changed');

        return back();
    }

    /** Which side of the pairing this user plays ('one' or 'two'), or null. */
    private function sideFor(Tournament $tournament, TournamentGame $game, int $userId): ?string
    {
        foreach (['one' => $game->player_one_id, 'two' => $game->player_two_id] as $side => $playerId) {
            if ($playerId === null) {
                continue;
            }
            $player = TournamentPlayer::where('tournament_id', $tournament->id)->with('members')->find($playerId);
            if ($player !== null && in_array($userId, $player->linkedUserIds(), true)) {
                return $side;
            }
        }

        return null;
    }

    /** Alternating Leaders: a master can't be brought again in a later round of the same event. */
    private function leaderAlreadyPlayed(Tournament $tournament, TournamentGame $game, string $side, string $name, string $title): ?string
    {
        if (empty($tournament->activeVariants()['alternating_leaders'])) {
            return null;
        }

        $playerId = $game->{"player_{$side}_id"};
        $previous = TournamentGame::query()
            ->whereHas('round', fn ($q) => $q->where('tournament_id', $tournament->id)->where('round_number', '<', $game->round->round_number))
            ->where(fn ($q) => $q->where('player_one_id', $playerId)->orWhere('player_two_id', $playerId))
            ->with('round:id,round_number')
            ->get()
            ->first(function (TournamentGame $g) use ($playerId, $name, $title) {
                $mine = $g->player_one_id === $playerId ? 'one' : 'two';
                $used = $g->{"player_{$mine}_title"} ?: $g->{"player_{$mine}_master"};

                return $used !== null && in_array($used, [$title, $name], true);
            });

        return $previous ? "{$title} was already played in Round {$previous->round->round_number} (Alternating Leaders is on)." : null;
    }

    private function factionLabel(string $slug): string
    {
        return FactionEnum::tryFrom($slug)?->label() ?? $slug;
    }
}
