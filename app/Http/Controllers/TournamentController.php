<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Tournament\BroadcastsTournamentUpdates;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentStandingsService;
use App\Services\TournamentStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

class TournamentController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(
        private readonly TournamentStandingsService $standings,
        private readonly TournamentStateMachine $stateMachine,
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

        // Auto-create planned rounds in Setup so TOs can configure scenarios upfront
        for ($i = 1; $i <= $tournament->planned_rounds; $i++) {
            TournamentRound::create([
                'tournament_id' => $tournament->id,
                'round_number' => $i,
                'status' => TournamentRoundStatusEnum::Setup,
            ]);
        }

        return redirect()->route('tournaments.manage', $tournament);
    }

    public function manage(Tournament $tournament): Response|ResponseFactory
    {
        $this->authorize('manage', $tournament);

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
        $this->authorize('manage', $tournament);

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

        $this->broadcastUpdate($tournament, 'settings_updated');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament)
    {
        // Deletion is destructive: creator-only (or super_admin via the policy's
        // before() hook). Invited organizers cannot delete.
        $this->authorize('delete', $tournament);

        if ($tournament->status !== TournamentStatusEnum::Draft) {
            return response()->json(['error' => 'Only draft tournaments can be deleted'], 422);
        }

        $tournament->delete();

        return redirect()->route('tournaments.index');
    }

    public function updateStatus(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = TournamentStatusEnum::from($validated['status']);

        if ($error = $this->stateMachine->canTransitionTournamentTo($tournament, $newStatus)) {
            return response()->json(['error' => $error], 422);
        }

        $tournament->update(['status' => $newStatus]);

        $this->broadcastUpdate($tournament, 'status_changed');

        return response()->json(['success' => true]);
    }
}
