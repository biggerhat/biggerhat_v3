<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\DeploymentEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentGame;
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

        // Scenario edits only allowed during Setup, or during InProgress before
        // any game is reported. Once a game has been scored, the scenario is locked.
        $scenarioFields = array_intersect_key($validated, array_flip(['deployment', 'strategy_id', 'scheme_pool']));
        if (! empty($scenarioFields) && $tournament->status === TournamentStatusEnum::Active) {
            if ($round->status === TournamentRoundStatusEnum::Completed) {
                return response()->json(['error' => 'Cannot modify scenario after round is completed'], 422);
            }
            if ($round->status === TournamentRoundStatusEnum::InProgress) {
                $hasReportedGames = $round->games()
                    ->where('result', '!=', TournamentGameResultEnum::Pending)
                    ->where('is_bye', false)
                    ->exists();
                if ($hasReportedGames) {
                    return response()->json(['error' => 'Cannot modify scenario after games have been reported'], 422);
                }
            }
        }

        if (isset($validated['status'])) {
            $newStatus = TournamentRoundStatusEnum::from($validated['status']);

            if ($error = $this->stateMachine->canTransitionRoundTo($round, $newStatus)) {
                return response()->json(['error' => $error], 422);
            }

            // Stamp transition timestamps when entering each state
            if ($newStatus === TournamentRoundStatusEnum::InProgress) {
                $validated['started_at'] = $round->started_at ?? now();
            }
            if ($newStatus === TournamentRoundStatusEnum::Completed) {
                $validated['completed_at'] = $round->completed_at ?? now();
            }

            $validated['status'] = $newStatus;
        }

        // Persist only fields the request actually included, plus the side
        // effects we just added above.
        $requestedKeys = array_flip(array_keys($request->all()));
        if (isset($validated['started_at'])) {
            $requestedKeys['started_at'] = true;
        }
        if (isset($validated['completed_at'])) {
            $requestedKeys['completed_at'] = true;
        }
        $fieldsToUpdate = array_intersect_key($validated, $requestedKeys);
        $round->update($fieldsToUpdate);

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

        $this->broadcastUpdate($tournament, 'round_updated');

        return response()->json(['success' => true]);
    }

    public function pair(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        if ($error = $this->stateMachine->canPairRound($tournament, $round)) {
            return response()->json(['error' => $error], 422);
        }

        $pairingsCount = DB::transaction(function () use ($tournament, $round) {
            // Re-pairing wipes prior games AND any linked tracker games to avoid orphans.
            $this->trackerGames->destroyForRound($round);
            $round->games()->delete();

            $tournament->load('players');
            $pairings = $this->pairing->generatePairings($tournament, $round);

            $players = $tournament->players->keyBy('id');
            $tableNumber = 1;
            foreach ($pairings as $pairing) {
                TournamentGame::create([
                    'tournament_round_id' => $round->id,
                    'player_one_id' => $pairing['player_one_id'],
                    'player_two_id' => $pairing['player_two_id'],
                    'is_bye' => $pairing['is_bye'],
                    'result' => $pairing['is_bye'] ? TournamentGameResultEnum::Completed : TournamentGameResultEnum::Pending,
                    'table_number' => $pairing['is_bye'] ? null : $tableNumber++,
                    'player_one_faction' => $players->get($pairing['player_one_id'])?->getRawOriginal('faction'),
                    'player_two_faction' => $pairing['player_two_id']
                        ? $players->get($pairing['player_two_id'])?->getRawOriginal('faction')
                        : null,
                ]);
            }

            $this->trackerGames->createForRound($tournament, $round);

            return count($pairings);
        });

        $this->broadcastUpdate($tournament, 'pairings_generated');

        return response()->json(['success' => true, 'pairings_count' => $pairingsCount]);
    }
}
