<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Enums\TournamentTiebreakerEnum;
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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
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
            ? Tournament::forOrganizer($userId)
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
            'players.user:id,name,meta_id',
            'players.user.meta:id,name',
            'players.meta:id,name',
            'rsvps.user:id,name',
            'rounds.games.playerOne:id,display_name,faction,user_id',
            'rounds.games.playerTwo:id,display_name,faction,user_id',
            // Don't column-restrict trackerGame — Game has appended attributes
            // (season_label) that need other columns to compute.
            'rounds.games.trackerGame',
            'rounds.strategy',
            'organizers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);

        // Fetch every scheme referenced by any round in a single query instead
        // of one query per round (previously N+1).
        $allSchemeIds = collect($tournament->rounds)
            ->flatMap(fn (TournamentRound $r) => is_array($r->scheme_pool) ? $r->scheme_pool : [])
            ->unique()
            ->values()
            ->all();
        $schemesById = empty($allSchemeIds)
            ? collect()
            : Scheme::whereIn('id', $allSchemeIds)->get()->keyBy('id');

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

            $schemes = [];
            foreach ((array) $round->scheme_pool as $id) {
                /** @var Scheme|null $s */
                $s = $schemesById->get($id);
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

        // Most-scored scheme across all tracker-linked games. Tournament-side
        // scoring records strategy/scheme as a sum, not per-pick — so the only
        // place the scheme breakdown lives is in GameTurn rows on the linked
        // tracker games. Coverage is partial (games where neither player has a
        // BiggerHat account aren't tracked), but it's enough to surface the
        // most-popular scheme of the event.
        $trackerGameIds = collect($roundsData)
            ->flatMap(fn ($r) => collect($r['games'])->pluck('game_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $schemeStats = null;
        if ($trackerGameIds && $schemesById->isNotEmpty()) {
            // Use the table query builder (not Eloquent) so PHPStan doesn't try
            // to map the SUM/COUNT projections back onto GameTurn properties.
            $top = \DB::table('game_turns')
                ->whereIn('game_id', $trackerGameIds)
                ->where('scheme_action', 'scored')
                ->where('scheme_points', '>', 0)
                ->whereNotNull('scheme_id')
                ->selectRaw('scheme_id, SUM(scheme_points) as total_points, COUNT(*) as scoring_turns')
                ->groupBy('scheme_id')
                ->orderByDesc('total_points')
                ->first();

            if ($top) {
                /** @var Scheme|null $scheme */
                $scheme = $schemesById->get($top->scheme_id);
                if ($scheme) {
                    $schemeStats = [
                        'most_scored' => [
                            'id' => $scheme->id,
                            'name' => $scheme->name,
                            'total_points' => (int) $top->total_points,
                            'scoring_turns' => (int) $top->scoring_turns,
                        ],
                    ];
                }
            }
        }

        // Unset loaded rounds to avoid double-serializing alongside roundsData.
        $tournament->unsetRelation('rounds');

        return inertia('Tournaments/View', [
            'tournament' => $tournament,
            'rounds' => $roundsData,
            'standings' => $standings,
            'factions' => FactionEnum::buildDetails(),
            'scheme_stats' => $schemeStats,
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
            // Bye-game scoring (defaults to Gaining Grounds 3 / +4 / 6).
            'bye_tp' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'bye_diff' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bye_vp' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'tiebreaker_mode' => ['sometimes', Rule::enum(TournamentTiebreakerEnum::class)],
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
            'players.user:id,name,meta_id',
            'players.user.meta:id,name',
            'players.meta:id,name',
            'rsvps.user:id,name',
            'rounds.games.playerOne:id,display_name,faction,user_id',
            'rounds.games.playerTwo:id,display_name,faction,user_id',
            // Don't column-restrict trackerGame — Game appends `season_label`,
            // whose accessor reads `$this->season`. A restricted select that
            // omits `season` crashes under strict-attribute mode the moment
            // Inertia serializes the model. Same comment as `view()`.
            'rounds.games.trackerGame',
            'rounds.strategy:id,name',
            'organizers:id,name',
        ]);

        $standings = $this->standings->compute($tournament);

        $seasons = collect(PoolSeasonEnum::cases())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        // Reference data needed for editing (scenario picker, score dialog, master
        // dropdown) — deferred so the page renders immediately and these batch
        // in a follow-up request. Grouped under "scenario" so the Inertia client
        // fires one extra request for all three instead of three separate ones.
        $strategiesDeferred = Inertia::defer(function () use ($tournament) {
            /** @var \App\Enums\PoolSeasonEnum $season */
            $season = $tournament->season;
            $results = [];
            foreach (Strategy::forSeason($season)->orderBy('name')->get() as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url];
            }

            return $results;
        }, 'scenario');

        $schemesDeferred = Inertia::defer(function () use ($tournament) {
            /** @var \App\Enums\PoolSeasonEnum $season */
            $season = $tournament->season;
            $results = [];
            foreach (Scheme::forSeason($season)->orderBy('name')->get() as $s) {
                $results[] = ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'image_url' => $s->image_url, 'prerequisite' => $s->prerequisite, 'reveal' => $s->reveal, 'scoring' => $s->scoring];
            }

            return $results;
        }, 'scenario');

        // Masters grouped by name with titles — only used in the score-entry
        // dialog, so defer under its own group.
        $mastersDeferred = Inertia::defer(function () {
            $characters = \App\Models\Character::standard()->where('station', 'master')
                ->where('is_hidden', false)
                ->orderBy('name')->orderBy('title')
                ->get(['id', 'name', 'title', 'display_name', 'faction', 'second_faction']);

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
        }, 'masters');

        return inertia('Tournaments/Manage', [
            'tournament' => $tournament,
            'standings' => $standings,
            'seasons' => $seasons,
            'factions' => fn () => FactionEnum::buildDetails(),
            'encounter_types' => collect(\App\Enums\EncounterTypeEnum::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'all_deployments' => collect(DeploymentEnum::cases())->map(fn (DeploymentEnum $d) => ['value' => $d->value, 'label' => $d->label(), 'description' => $d->description(), 'image_url' => $d->imageUrl()]),
            // Deferred below — page renders without these, a follow-up request
            // fills them in. Grouping means strategies + schemes batch into one
            // request, masters into another.
            'all_strategies' => $strategiesDeferred,
            'all_schemes' => $schemesDeferred,
            'masters' => $mastersDeferred,
        ]);
    }

    /**
     * CSV dump of every non-bye game in the tournament — TOs use this to
     * manually transcribe results into Longshanks (no public API exists), or
     * just as an event archive. Round-major sort, one row per pairing.
     */
    public function exportCsv(Tournament $tournament): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('manage', $tournament);

        $tournament->load([
            'rounds.games.playerOne:id,display_name,faction',
            'rounds.games.playerTwo:id,display_name,faction',
        ]);

        $filename = 'tournament-'.$tournament->uuid.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($tournament) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'round',
                'table',
                'player_one',
                'player_one_faction',
                'player_one_master',
                'player_one_vp',
                'player_one_strategy_vp',
                'player_one_scheme_vp',
                'player_two',
                'player_two_faction',
                'player_two_master',
                'player_two_vp',
                'player_two_strategy_vp',
                'player_two_scheme_vp',
                'result',
                'is_bye',
                'is_forfeit',
            ]);

            /** @var TournamentRound $round */
            foreach ($tournament->rounds->sortBy('round_number') as $round) {
                foreach ($round->games as $game) {
                    // playerTwo is nullable on byes; playerOne is required by
                    // schema. Reach via the relation only when present.
                    $p1 = $game->playerOne;
                    $p2 = $game->playerTwo;
                    fputcsv($out, [
                        $round->round_number,
                        $game->table_number,
                        $p1 ? $p1->display_name : null,
                        $game->player_one_faction ?: ($p1 ? $p1->getRawOriginal('faction') : null),
                        $game->player_one_title ?: $game->player_one_master,
                        $game->player_one_vp,
                        $game->player_one_strategy_vp,
                        $game->player_one_scheme_vp,
                        $p2 ? $p2->display_name : null,
                        $game->player_two_faction ?: ($p2 ? $p2->getRawOriginal('faction') : null),
                        $game->player_two_title ?: $game->player_two_master,
                        $game->player_two_vp,
                        $game->player_two_strategy_vp,
                        $game->player_two_scheme_vp,
                        $game->result->value,
                        $game->is_bye ? 'true' : 'false',
                        $game->is_forfeit ? 'true' : 'false',
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function update(Request $request, Tournament $tournament)
    {
        $this->authorize('manage', $tournament);

        /** @var TournamentStatusEnum $status */
        $status = $tournament->status;
        // After the tournament starts, most settings are locked. The
        // tiebreaker mode is the exception — TOs can flip it any time
        // since standings recompute live (no stored ranking).
        $isLocked = ! $status->isEditable();
        if ($isLocked && ! $request->has('tiebreaker_mode')) {
            return response()->json(['error' => 'Tournament settings are locked after starting'], 422);
        }
        if ($isLocked) {
            // Only allow the tiebreaker_mode key through.
            $request->replace(['tiebreaker_mode' => $request->input('tiebreaker_mode')]);
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
            'bye_tp' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'bye_diff' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'bye_vp' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'tiebreaker_mode' => ['sometimes', Rule::enum(TournamentTiebreakerEnum::class)],
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

        // Broadcast BEFORE deletion so any co-organizer viewing the Manage or
        // public View page gets bounced to the index rather than 404ing on
        // their next partial reload. Payload captures the uuid for the channel
        // even after the tournament row is gone.
        $this->broadcastUpdate($tournament, 'tournament_deleted');

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
