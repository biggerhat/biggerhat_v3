<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\DeploymentEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentPairingService;
use App\Services\TournamentStateMachine;
use App\Services\TournamentTrackerGameFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentRoundController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(
        private readonly TournamentPairingService $pairing,
        private readonly TournamentStateMachine $stateMachine,
        private readonly TournamentTrackerGameFactory $trackerGames,
    ) {}

    public function store(Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $allowedStatuses = [TournamentStatusEnum::Draft, TournamentStatusEnum::Registration, TournamentStatusEnum::Active];
        /** @var TournamentStatusEnum $currentStatus */
        $currentStatus = $tournament->status;
        if (! in_array($currentStatus, $allowedStatuses)) {
            return response()->json(['error' => 'Cannot create rounds in this status'], 422);
        }

        $nextRound = ($tournament->rounds()->max('round_number') ?? 0) + 1;

        // Bump planned_rounds when extending past the original cap
        if ($nextRound > $tournament->planned_rounds) {
            $tournament->update(['planned_rounds' => $nextRound]);
        }

        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'round_number' => $nextRound,
            'status' => TournamentRoundStatusEnum::Setup,
        ]);

        $this->broadcastUpdate($tournament, 'round_created');

        return response()->json(['success' => true, 'round' => $round]);
    }

    public function generateAll(Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $existingMax = $tournament->rounds()->max('round_number') ?? 0;
        $created = 0;

        for ($i = $existingMax + 1; $i <= $tournament->planned_rounds; $i++) {
            TournamentRound::create([
                'tournament_id' => $tournament->id,
                'round_number' => $i,
                'status' => TournamentRoundStatusEnum::Setup,
            ]);
            $created++;
        }

        if ($created > 0) {
            $this->broadcastUpdate($tournament, 'rounds_generated');
        }

        return response()->json(['success' => true, 'created' => $created]);
    }

    public function destroy(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        // Never delete a round that is currently in progress — that's
        // destructive to live game state. Setup and Completed rounds can be
        // removed (Setup is empty by definition; Completed is at the TO's
        // discretion and useful for fixing bad data).
        if ($round->status === TournamentRoundStatusEnum::InProgress) {
            return response()->json(['error' => 'Cannot delete a round that is in progress.'], 422);
        }

        // Also clean up any linked Game Tracker games to avoid orphans.
        $this->trackerGames->destroyForRound($round);
        $round->delete();

        // If we deleted the last round, drop planned_rounds back to match.
        $maxRound = (int) ($tournament->rounds()->max('round_number') ?? 0);
        if ($maxRound < $tournament->planned_rounds) {
            $tournament->update(['planned_rounds' => max(1, $maxRound)]);
        }

        $this->broadcastUpdate($tournament, 'round_deleted');

        return response()->json(['success' => true]);
    }

    public function update(Request $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validate([
            'deployment' => ['nullable', 'string'],
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'scheme_pool' => ['nullable', 'array', 'min:3', 'max:3'],
            'scheme_pool.*' => ['integer', 'exists:schemes,id'],
            'status' => ['sometimes', 'string'],
        ]);

        // Scenario edits gated by the state machine. Only checked when the
        // user actually included scenario fields in the request.
        $scenarioFields = array_intersect_key($validated, array_flip(['deployment', 'strategy_id', 'scheme_pool']));
        if (! empty($scenarioFields)) {
            if ($error = $this->stateMachine->canEditScenario($tournament, $round)) {
                return response()->json(['error' => $error], 422);
            }
        }

        // Build the persisted set explicitly: only fields the user submitted,
        // plus server-set side-effects (started_at / completed_at) when status
        // transitions across InProgress / Completed.
        $userFields = array_intersect_key($validated, array_flip(['deployment', 'strategy_id', 'scheme_pool']));

        if (isset($validated['status'])) {
            $newStatus = TournamentRoundStatusEnum::from($validated['status']);

            if ($error = $this->stateMachine->canTransitionRoundTo($round, $newStatus)) {
                return response()->json(['error' => $error], 422);
            }

            $userFields['status'] = $newStatus;

            if ($newStatus === TournamentRoundStatusEnum::InProgress) {
                $userFields['started_at'] = $round->started_at ?? now();
            }
            if ($newStatus === TournamentRoundStatusEnum::Completed) {
                $userFields['completed_at'] = $round->completed_at ?? now();
            }
        }

        $round->update($userFields);

        // When the round transitions into InProgress, mark every bye game as
        // Completed. Byes are scoreless so there's nothing to enter — but we
        // intentionally hold them at Pending until the TO clicks Start so
        // the bye player's points don't show up on the standings prematurely.
        if (isset($newStatus) && $newStatus === TournamentRoundStatusEnum::InProgress) {
            $round->games()
                ->where('is_bye', true)
                ->where('result', \App\Enums\TournamentGameResultEnum::Pending)
                ->update(['result' => \App\Enums\TournamentGameResultEnum::Completed]);
        }

        // If the scenario changed, push it down to any tracker games already
        // linked to this round so participants see the updated scenario.
        if (! empty($scenarioFields)) {
            $this->trackerGames->syncScenarioForRound($round->fresh());
        }

        $this->broadcastUpdate($tournament, 'round_updated');

        return response()->json(['success' => true]);
    }

    public function randomize(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        /** @var \App\Enums\PoolSeasonEnum $season */
        $season = $tournament->season;
        $strategies = Strategy::forSeason($season)->get();
        $schemes = Scheme::forSeason($season)->get();
        $deployments = DeploymentEnum::cases();

        $round->update([
            'strategy_id' => $strategies->isNotEmpty() ? $strategies->random()->id : null,
            'deployment' => $deployments[array_rand($deployments)]->value,
            'scheme_pool' => $schemes->count() >= 3
                ? $schemes->random(3)->pluck('id')->toArray()
                : $schemes->pluck('id')->toArray(),
        ]);

        // Propagate the new scenario to any tracker games already created for this round.
        $this->trackerGames->syncScenarioForRound($round->fresh());

        $this->broadcastUpdate($tournament, 'round_updated');

        return response()->json(['success' => true]);
    }

    public function pair(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        if ($error = $this->stateMachine->canPairRound($tournament, $round)) {
            return response()->json(['error' => $error], 422);
        }

        // Wipe auto-paired games + tracker games, regenerate, recreate trackers,
        // then sync scenario down (handled inside createForRound).
        $pairingsCount = DB::transaction(function () use ($tournament, $round): int {
            $this->trackerGames->destroyForRound($round, autoOnly: true);
            $round->games()->where('is_manual', false)->delete();

            $count = $this->pairing->regeneratePairings($tournament, $round);
            $this->trackerGames->createForRound($tournament, $round);

            return $count;
        });

        $this->broadcastUpdate($tournament, 'pairings_generated');

        return response()->json(['success' => true, 'pairings_count' => $pairingsCount]);
    }
}
