<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Events\TournamentUpdated;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\TournamentRsvp;
use App\Services\TournamentPairingService;
use App\Services\TournamentStandingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

class TournamentController extends Controller
{
    public function __construct(
        private readonly TournamentStandingsService $standings,
        private readonly TournamentPairingService $pairing,
    ) {}

    public function index(): Response|ResponseFactory
    {
        $userId = Auth::id();

        $myTournaments = $userId
            ? Tournament::where('creator_id', $userId)
                ->orWhereHas('organizers', fn ($q) => $q->where('user_id', $userId))
                ->with('creator:id,name')
                ->withCount('players')
                ->latest('event_date')
                ->get()
            : collect();

        $publicTournaments = Tournament::when($userId, fn ($q) => $q->where('creator_id', '!=', $userId))
            ->with('creator:id,name')
            ->withCount('players')
            ->latest('event_date')
            ->take(20)
            ->get();

        return inertia('Tournaments/Index', [
            'my_tournaments' => $myTournaments,
            'public_tournaments' => $publicTournaments,
        ]);
    }

    public function view(Tournament $tournament): Response|ResponseFactory
    {
        $tournament->load([
            'players.user:id,name',
            'rsvps.user:id,name',
            'rounds.games.playerOne',
            'rounds.games.playerTwo',
            'rounds.games.trackerGame:id,uuid',
            'rounds.strategy',
            'organizers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);

        // Resolve each round's deployment/strategy/schemes into full objects with images
        $roundsData = [];
        foreach ($tournament->rounds as $round) {
            /** @var TournamentRound $round */
            $deployment = null;
            /** @var \App\Enums\DeploymentEnum|null $deployEnum */
            $deployEnum = $round->deployment;
            if ($deployEnum) {
                $deployment = [
                    'value' => $deployEnum->value,
                    'label' => $deployEnum->label(),
                    'description' => $deployEnum->description(),
                    'image_url' => $deployEnum->imageUrl(),
                ];
            }

            /** @var Strategy|null $strategyModel */
            $strategyModel = $round->strategy;
            $strategy = null;
            if ($strategyModel) {
                $strategyModel->append('image_url');
                $strategy = [
                    'id' => $strategyModel->id,
                    'name' => $strategyModel->name,
                    'image_url' => $strategyModel->image_url,
                ];
            }

            /** @var array|null $schemePool */
            $schemePool = $round->scheme_pool;
            $schemes = [];
            if (is_array($schemePool) && count($schemePool) > 0) {
                $schemeModels = Scheme::whereIn('id', $schemePool)->get();
                foreach ($schemePool as $id) {
                    $s = $schemeModels->firstWhere('id', $id);
                    if ($s) {
                        $schemes[] = [
                            'id' => $s->id,
                            'name' => $s->name,
                            'image_url' => $s->image_url,
                            'prerequisite' => $s->prerequisite,
                            'reveal' => $s->reveal,
                            'scoring' => $s->scoring,
                        ];
                    }
                }
            }

            /** @var \App\Enums\TournamentRoundStatusEnum $roundStatus */
            $roundStatus = $round->status;
            $roundsData[] = [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'status' => $roundStatus->value,
                'deployment' => $deployment,
                'strategy' => $strategy,
                'schemes' => $schemes,
                'games' => $round->games,
            ];
        }

        // Unset loaded rounds/rsvps to avoid double-serializing with the custom roundsData
        $tournament->unsetRelation('rounds');

        return inertia('Tournaments/View', [
            'tournament' => $tournament,
            'rounds' => $roundsData,
            'standings' => $standings,
            'factions' => FactionEnum::buildDetails(),
        ]);
    }

    public function create(): Response|ResponseFactory
    {
        $seasons = collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        $encounterTypes = collect(\App\Enums\EncounterTypeEnum::cases())->map(fn ($e) => [
            'value' => $e->value,
            'label' => $e->label(),
        ]);

        return inertia('Tournaments/Create', [
            'seasons' => $seasons,
            'encounter_types' => $encounterTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'encounter_size' => ['required', 'integer', 'min:20', 'max:100'],
            'encounter_type' => ['sometimes', 'string'],
            'planned_rounds' => ['required', 'integer', 'min:1', 'max:7'],
            'season' => ['required', 'string'],
            'event_date' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'round_time_limit' => ['sometimes', 'integer', 'min:30', 'max:300'],
        ]);

        $tournament = Tournament::create([
            ...$validated,
            'creator_id' => Auth::id(),
            'status' => TournamentStatusEnum::Draft,
        ]);

        $tournament->organizers()->attach(Auth::id());

        return redirect()->route('tournaments.manage', $tournament);
    }

    public function manage(Tournament $tournament): Response|ResponseFactory
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $tournament->load([
            'players.user:id,name',
            'rsvps.user:id,name',
            'rounds.games.playerOne',
            'rounds.games.playerTwo',
            'rounds.strategy:id,name',
            'organizers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);

        $seasons = collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        // Masters grouped by name with titles
        $masters = function () {
            $characters = \App\Models\Character::where('station', 'master')
                ->where('is_hidden', false)
                ->orderBy('name')->orderBy('title')
                ->get();

            $grouped = [];
            foreach ($characters->groupBy('name') as $name => $group) {
                $first = $group->first();
                $grouped[] = [
                    'name' => $name,
                    'faction' => $first->getRawOriginal('faction'),
                    'second_faction' => $first->getRawOriginal('second_faction'),
                    'titles' => $group->map(fn ($c) => [
                        'id' => $c->id,
                        'display_name' => $c->display_name,
                        'title' => $c->title,
                    ])->values(),
                ];
            }

            return $grouped; // @phpstan-ignore return.type
        };

        return inertia('Tournaments/Manage', [
            'tournament' => $tournament,
            'standings' => $standings,
            'seasons' => $seasons,
            'factions' => fn () => FactionEnum::buildDetails(),
            'encounter_types' => collect(\App\Enums\EncounterTypeEnum::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'masters' => $masters,
            'all_strategies' => function () use ($tournament) {
                /** @var \App\Enums\PoolSeasonEnum $season */
                $season = $tournament->season;
                $results = [];
                foreach (Strategy::forSeason($season)->orderBy('name')->get() as $s) {
                    $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url];
                }

                return $results;
            },
            'all_schemes' => function () use ($tournament) {
                /** @var \App\Enums\PoolSeasonEnum $season */
                $season = $tournament->season;
                $results = [];
                foreach (Scheme::forSeason($season)->orderBy('name')->get() as $s) {
                    $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url, 'prerequisite' => $s->prerequisite, 'reveal' => $s->reveal, 'scoring' => $s->scoring];
                }

                return $results;
            },
            'all_deployments' => fn () => collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label(), 'description' => $d->description(), 'image_url' => $d->imageUrl()]),
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->isEditable()) {
            return response()->json(['error' => 'Tournament settings are locked after starting'], 422);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'encounter_size' => ['sometimes', 'integer', 'min:20', 'max:100'],
            'encounter_type' => ['sometimes', 'string'],
            'planned_rounds' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'season' => ['sometimes', 'string'],
            'event_date' => ['sometimes', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'round_time_limit' => ['sometimes', 'integer', 'min:30', 'max:300'],
        ]);

        $tournament->update($validated);

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament)
    {
        if ($tournament->creator_id !== Auth::id()) {
            abort(403);
        }

        if ($tournament->status !== TournamentStatusEnum::Draft) {
            return response()->json(['error' => 'Only draft tournaments can be deleted'], 422);
        }

        $tournament->delete();

        return redirect()->route('tournaments.index');
    }

    public function updateStatus(Request $request, Tournament $tournament): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = TournamentStatusEnum::from($validated['status']);

        /** @var TournamentStatusEnum $currentStatus */
        $currentStatus = $tournament->status;
        $allowed = $currentStatus->validTransitions();
        if (! in_array($newStatus, $allowed)) {
            return response()->json(['error' => 'Invalid status transition'], 422);
        }

        // ── Transition: Draft → Registration ──
        // RSVP closes, TO can now add players
        if ($newStatus === TournamentStatusEnum::Registration) {
            // No prerequisites — just close RSVP
        }

        // ── Transition: Registration → Active (Started) ──
        // Locks: scenarios, tournament info, registration
        if ($newStatus === TournamentStatusEnum::Active) {
            $missingFaction = $tournament->players()->whereNull('faction')->count();
            if ($missingFaction > 0) {
                return response()->json(['error' => "{$missingFaction} player(s) missing faction selection"], 422);
            }
            if ($tournament->players()->count() < 2) {
                return response()->json(['error' => 'Need at least 2 players to start'], 422);
            }
        }

        // ── Transition: Active → Completed ──
        // All rounds must be completed
        if ($newStatus === TournamentStatusEnum::Completed) {
            $incompleteRounds = $tournament->rounds()
                ->where('status', '!=', TournamentRoundStatusEnum::Completed)
                ->count();
            if ($incompleteRounds > 0) {
                return response()->json(['error' => "{$incompleteRounds} round(s) still in progress. Finish all rounds first."], 422);
            }
            if ($tournament->rounds()->count() === 0) {
                return response()->json(['error' => 'No rounds have been played'], 422);
            }
        }

        $tournament->update(['status' => $newStatus]);

        $this->broadcastUpdate($tournament, 'status_changed');

        return response()->json(['success' => true]);
    }

    // ─── Organizers ───

    public function addOrganizer(Request $request, Tournament $tournament): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        if ($tournament->organizers()->where('user_id', $validated['user_id'])->exists()) {
            return response()->json(['error' => 'Already an organizer'], 422);
        }

        $tournament->organizers()->attach($validated['user_id']);

        return response()->json(['success' => true]);
    }

    public function removeOrganizer(Tournament $tournament, int $userId): JsonResponse
    {
        if ($tournament->creator_id !== Auth::id()) {
            abort(403, 'Only the creator can remove organizers');
        }

        if ($userId === $tournament->creator_id) {
            return response()->json(['error' => 'Cannot remove the creator'], 422);
        }

        $tournament->organizers()->detach($userId);

        return response()->json(['success' => true]);
    }

    // ─── RSVP ───

    public function rsvp(Request $request, Tournament $tournament): JsonResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRsvp()) {
            return response()->json(['error' => 'RSVP is not open for this tournament'], 422);
        }

        $userId = Auth::id();
        if (! $userId) {
            abort(401);
        }

        if ($tournament->rsvps()->where('user_id', $userId)->exists()) {
            return response()->json(['error' => 'Already RSVPed'], 422);
        }

        TournamentRsvp::create([
            'tournament_id' => $tournament->id,
            'user_id' => $userId,
        ]);

        $this->broadcastUpdate($tournament, 'rsvp_added');

        return response()->json(['success' => true]);
    }

    public function cancelRsvp(Tournament $tournament): JsonResponse
    {
        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRsvp()) {
            return response()->json(['error' => 'Cannot cancel RSVP at this time'], 422);
        }

        $tournament->rsvps()->where('user_id', Auth::id())->delete();

        return response()->json(['success' => true]);
    }

    // ─── Players ───

    public function addPlayer(Request $request, Tournament $tournament): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        if (! $status->allowsRegistration() && ! $status->isEditable()) {
            return response()->json(['error' => 'Cannot add players in this tournament state'], 422);
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'faction' => ['required', 'string'],
            'is_ringer' => ['sometimes', 'boolean'],
        ]);

        // Enforce single ringer
        if (! empty($validated['is_ringer']) && $tournament->players()->where('is_ringer', true)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        $player = TournamentPlayer::create([
            'tournament_id' => $tournament->id,
            'display_name' => $validated['display_name'],
            'user_id' => $validated['user_id'] ?? null,
            'faction' => $validated['faction'] ?? null,
            'is_ringer' => $validated['is_ringer'] ?? false,
        ]);

        return response()->json(['success' => true, 'player' => $player]);
    }

    public function removePlayer(Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($player->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        $player->delete();

        return response()->json(['success' => true]);
    }

    public function updatePlayer(Request $request, Tournament $tournament, TournamentPlayer $player): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($player->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'faction' => ['nullable', 'string'],
            'is_ringer' => ['sometimes', 'boolean'],
            'is_disqualified' => ['sometimes', 'boolean'],
            'dropped_after_round' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        // Enforce single ringer
        if (! empty($validated['is_ringer']) && ! $player->is_ringer
            && $tournament->players()->where('is_ringer', true)->where('id', '!=', $player->id)->exists()) {
            return response()->json(['error' => 'Tournament already has a ringer'], 422);
        }

        if (isset($validated['is_disqualified']) && $validated['is_disqualified'] && ! $player->is_disqualified) {
            $validated['disqualified_at'] = now();
        }

        $player->update($validated);

        return response()->json(['success' => true]);
    }

    // ─── Rounds ───

    public function createRound(Tournament $tournament): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $allowedStatuses = [TournamentStatusEnum::Draft, TournamentStatusEnum::Registration, TournamentStatusEnum::Active];
        /** @var TournamentStatusEnum $currentStatus */
        $currentStatus = $tournament->status;
        if (! in_array($currentStatus, $allowedStatuses)) {
            return response()->json(['error' => 'Cannot create rounds in this status'], 422);
        }

        $nextRound = ($tournament->rounds()->max('round_number') ?? 0) + 1;

        if ($nextRound > $tournament->planned_rounds) {
            return response()->json(['error' => 'All planned rounds created'], 422);
        }

        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'round_number' => $nextRound,
            'status' => TournamentRoundStatusEnum::Setup,
        ]);

        return response()->json(['success' => true, 'round' => $round]);
    }

    public function generateAllRounds(Tournament $tournament): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

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

        return response()->json(['success' => true, 'created' => $created]);
    }

    public function updateRound(Request $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        $validated = $request->validate([
            'deployment' => ['nullable', 'string'],
            'strategy_id' => ['nullable', 'exists:strategies,id'],
            'scheme_pool' => ['nullable', 'array', 'min:3', 'max:3'],
            'scheme_pool.*' => ['integer', 'exists:schemes,id'],
            'status' => ['sometimes', 'string'],
        ]);

        // Scenario config only allowed before tournament is started or when round is in setup
        $scenarioFields = array_intersect_key($validated, array_flip(['deployment', 'strategy_id', 'scheme_pool']));
        if (! empty($scenarioFields)) {
            if ($tournament->status === TournamentStatusEnum::Active && $round->status !== TournamentRoundStatusEnum::Setup) {
                return response()->json(['error' => 'Cannot modify scenario after round has started'], 422);
            }
        }

        if (isset($validated['status'])) {
            $newStatus = TournamentRoundStatusEnum::from($validated['status']);

            // Enforce round status transitions
            if ($newStatus === TournamentRoundStatusEnum::InProgress) {
                if ($round->status !== TournamentRoundStatusEnum::Setup) {
                    return response()->json(['error' => 'Round must be in setup to start'], 422);
                }
                if ($round->games()->count() === 0) {
                    return response()->json(['error' => 'Generate pairings before starting the round'], 422);
                }
                $validated['started_at'] = $round->started_at ?? now();
            }

            if ($newStatus === TournamentRoundStatusEnum::Completed) {
                if ($round->status !== TournamentRoundStatusEnum::InProgress) {
                    return response()->json(['error' => 'Round must be in progress to finish'], 422);
                }
                $unreported = $round->games()->where('result', TournamentGameResultEnum::Pending)->count();
                if ($unreported > 0) {
                    return response()->json(['error' => "{$unreported} game(s) not yet reported. Report all games before finishing the round."], 422);
                }
                $validated['completed_at'] = $round->completed_at ?? now();
            }

            $validated['status'] = $newStatus;
        }

        // Only update fields that were actually sent in the request
        $fieldsToUpdate = array_intersect_key($validated, $request->all());
        $round->update($fieldsToUpdate);

        $this->broadcastUpdate($tournament, 'round_updated');

        return response()->json(['success' => true]);
    }

    public function randomizeRoundScenario(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

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

        return response()->json(['success' => true]);
    }

    public function generatePairings(Tournament $tournament, TournamentRound $round): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        if ($round->status !== TournamentRoundStatusEnum::Setup) {
            return response()->json(['error' => 'Round must be in setup to generate pairings'], 422);
        }

        if ($tournament->status !== TournamentStatusEnum::Active) {
            return response()->json(['error' => 'Tournament must be active to generate pairings'], 422);
        }

        // Previous round must be completed (except for round 1)
        if ($round->round_number > 1) {
            /** @var TournamentRound|null $prevRound */
            $prevRound = $tournament->rounds()->where('round_number', $round->round_number - 1)->first();
            if (! $prevRound || $prevRound->status !== TournamentRoundStatusEnum::Completed) {
                return response()->json(['error' => 'Previous round must be completed before pairing'], 422);
            }
        }

        // Clear existing games for this round (re-generating)
        // Also clean up linked Game Tracker games to prevent orphans
        $linkedGameIds = $round->games()->whereNotNull('game_id')->pluck('game_id');
        if ($linkedGameIds->isNotEmpty()) {
            GamePlayer::whereIn('game_id', $linkedGameIds)->delete();
            Game::whereIn('id', $linkedGameIds)->forceDelete();
        }
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

        // Auto-create Game Tracker games for non-bye pairings
        $this->createTrackerGames($tournament, $round);

        $this->broadcastUpdate($tournament, 'pairings_generated');

        return response()->json(['success' => true, 'pairings_count' => count($pairings)]);
    }

    /**
     * Create Game Tracker games for each non-bye pairing in a round.
     */
    private function broadcastUpdate(Tournament $tournament, string $action = 'updated'): void
    {
        broadcast(new TournamentUpdated($tournament, $action))->toOthers();
    }

    private function createTrackerGames(Tournament $tournament, TournamentRound $round): void
    {
        $games = $round->games()->where('is_bye', false)->get();

        foreach ($games as $tournamentGame) {
            /** @var TournamentGame $tournamentGame */
            // Skip if a tracker game already exists
            if ($tournamentGame->game_id) {
                continue;
            }

            $playerOne = $tournamentGame->playerOne;
            $playerTwo = $tournamentGame->playerTwo;
            if (! $playerOne || ! $playerTwo) {
                continue;
            }

            $hasUserOne = (bool) $playerOne->user_id;
            $hasUserTwo = (bool) $playerTwo->user_id;

            // Need at least one BiggerHat user to create a game
            if (! $hasUserOne && ! $hasUserTwo) {
                continue;
            }

            $isSolo = ! $hasUserOne || ! $hasUserTwo;
            $creatorUserId = $hasUserOne ? $playerOne->user_id : $playerTwo->user_id;

            // Use the round's scenario
            $trackerGame = Game::create([
                'name' => "{$tournament->name} R{$round->round_number} T{$tournamentGame->table_number}",
                'encounter_size' => $tournament->encounter_size,
                'season' => $tournament->season->value,
                'strategy_id' => $round->strategy_id,
                'deployment' => $round->deployment?->value,
                'scheme_pool' => $round->scheme_pool ?? [],
                'status' => GameStatusEnum::SchemeSelect,
                'started_at' => now(),
                'creator_id' => $creatorUserId,
                'is_solo' => $isSolo,
                'is_observable' => true,
            ]);

            $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

            // Player 1 (higher-ranked or first in pairing)
            GamePlayer::create([
                'game_id' => $trackerGame->id,
                'user_id' => $playerOne->user_id,
                'slot' => 1,
                'role' => $roles[0],
                'faction' => $playerOne->getRawOriginal('faction'),
                'opponent_name' => $isSolo && ! $hasUserOne ? $playerOne->display_name : null,
                'scheme_pool' => $trackerGame->scheme_pool,
            ]);

            // Player 2
            GamePlayer::create([
                'game_id' => $trackerGame->id,
                'user_id' => $playerTwo->user_id,
                'slot' => 2,
                'role' => $roles[1],
                'faction' => $playerTwo->getRawOriginal('faction'),
                'opponent_name' => $isSolo && ! $hasUserTwo ? $playerTwo->display_name : null,
                'scheme_pool' => $trackerGame->scheme_pool,
            ]);

            // Link the tournament game to the tracker game
            $tournamentGame->update(['game_id' => $trackerGame->id]);
        }
    }

    // ─── Games / Scores ───

    public function createGame(Request $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        if ($round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        $validated = $request->validate([
            'player_one_id' => ['required', 'exists:tournament_players,id'],
            'player_two_id' => ['nullable', 'exists:tournament_players,id'],
            'is_bye' => ['sometimes', 'boolean'],
            'table_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $game = TournamentGame::create([
            'tournament_round_id' => $round->id,
            'player_one_id' => $validated['player_one_id'],
            'player_two_id' => $validated['player_two_id'] ?? null,
            'is_bye' => $validated['is_bye'] ?? false,
            'result' => ($validated['is_bye'] ?? false) ? TournamentGameResultEnum::Completed : TournamentGameResultEnum::Pending,
        ]);

        return response()->json(['success' => true, 'game' => $game]);
    }

    public function updateGameScore(Request $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        // Verify game belongs to this tournament
        $game->loadMissing('round');
        if ($game->round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        if ($game->round->status === TournamentRoundStatusEnum::Completed) {
            return response()->json(['error' => 'Scores are locked after the round is completed'], 422);
        }

        $validated = $request->validate([
            'player_one_faction' => ['nullable', 'string'],
            'player_one_master' => ['nullable', 'string', 'max:255'],
            'player_one_title' => ['nullable', 'string', 'max:255'],
            'player_one_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_one_vp' => ['required', 'integer', 'min:0', 'max:30'],
            'player_one_strategy_vp' => ['nullable', 'integer', 'min:0', 'max:15'],
            'player_one_scheme_vp' => ['nullable', 'integer', 'min:0', 'max:15'],
            'player_two_faction' => ['nullable', 'string'],
            'player_two_master' => ['nullable', 'string', 'max:255'],
            'player_two_title' => ['nullable', 'string', 'max:255'],
            'player_two_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_two_vp' => ['required', 'integer', 'min:0', 'max:30'],
            'player_two_strategy_vp' => ['nullable', 'integer', 'min:0', 'max:15'],
            'player_two_scheme_vp' => ['nullable', 'integer', 'min:0', 'max:15'],
        ]);

        $game->update([
            ...$validated,
            'result' => TournamentGameResultEnum::Completed,
        ]);

        $this->broadcastUpdate($tournament, 'score_updated');

        return response()->json(['success' => true]);
    }

    public function deleteGame(Tournament $tournament, TournamentGame $game): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $game->loadMissing('round');
        if ($game->round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        if ($game->round->getAttribute('status') !== TournamentRoundStatusEnum::Setup) {
            return response()->json(['error' => 'Can only remove games during round setup'], 422);
        }

        $game->delete();

        return response()->json(['success' => true]);
    }

    public function toggleForfeit(Request $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        if (! $tournament->isOrganizer(Auth::id())) {
            abort(403);
        }

        $game->loadMissing('round');
        if ($game->round->getAttribute('tournament_id') !== $tournament->id) {
            abort(403);
        }

        if ($game->is_forfeit) {
            // Remove forfeit
            $game->update([
                'is_forfeit' => false,
                'forfeit_player_id' => null,
                'result' => TournamentGameResultEnum::Pending,
                'player_one_vp' => null,
                'player_two_vp' => null,
            ]);
        } else {
            $validated = $request->validate([
                'forfeit_player_id' => ['required', 'exists:tournament_players,id'],
            ]);

            $game->update([
                'is_forfeit' => true,
                'forfeit_player_id' => $validated['forfeit_player_id'],
                'result' => TournamentGameResultEnum::Forfeited,
                'player_one_vp' => null,
                'player_two_vp' => null,
            ]);
        }

        $this->broadcastUpdate($tournament, 'forfeit_updated');

        return response()->json(['success' => true]);
    }
}
