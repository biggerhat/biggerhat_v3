<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\EncounterTypeEnum;
use App\Enums\PermissionEnum;
use App\Enums\PoolSeasonEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentStoreRequest;
use App\Http\Requests\Tournaments\TournamentUpdateRequest;
use App\Http\Requests\Tournaments\TournamentUpdateStatusRequest;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Tournament;
use App\Models\TournamentRound;
use App\Services\TournamentStateMachine;
use App\Tournaments\Payloads\TournamentPagePayloads;
use App\Tournaments\Rulesets\Ruleset;
use App\Tournaments\Rulesets\RulesetRegistry;
use App\Tournaments\WaitlistPromotions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;

class TournamentController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(
        private readonly TournamentStateMachine $stateMachine,
        private readonly TournamentPagePayloads $payloads,
    ) {}

    public function index(Request $request): Response|ResponseFactory
    {
        $userId = Auth::id();
        $registry = app(RulesetRegistry::class);

        // Find-an-event filters for the public list: free text (name or place), game format, and upcoming / past.
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'ruleset' => in_array($request->query('ruleset'), $registry->keys(), true) ? (string) $request->query('ruleset') : '',
            'when' => in_array($request->query('when'), ['upcoming', 'past'], true) ? (string) $request->query('when') : '',
        ];
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';

        $myTournaments = $userId
            ? Tournament::forOrganizer($userId)
                ->with('creator:id,name')
                ->withCount('players')
                ->latest('event_date')
                ->get()
            : collect();

        // Drafts stay private to their organizers (reachable by link for RSVPs) so open creation can't clutter the public list.
        $publicTournaments = Tournament::where('status', '!=', TournamentStatusEnum::Draft->value)
            ->when($userId, fn ($q) => $q->where('creator_id', '!=', $userId))
            ->with('creator:id,name')
            ->withCount('players')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('location', 'like', $like)))
            ->when($filters['ruleset'] !== '', fn ($q) => $q->where('ruleset', $filters['ruleset']))
            ->when($filters['when'] === 'upcoming', fn ($q) => $q->where('status', '!=', TournamentStatusEnum::Completed->value)->orderBy('event_date'))
            ->when($filters['when'] === 'past', fn ($q) => $q->where('status', TournamentStatusEnum::Completed->value)->latest('event_date'))
            ->when($filters['when'] === '', fn ($q) => $q->latest('event_date'))
            ->paginate(20)
            ->withQueryString();

        return inertia('Tournaments/Index', [
            'my_tournaments' => $myTournaments,
            'public_tournaments' => $publicTournaments->items(),
            'public_pagination' => [
                'current_page' => $publicTournaments->currentPage(),
                'last_page' => $publicTournaments->lastPage(),
                'total' => $publicTournaments->total(),
            ],
            'filters' => $filters,
            'ruleset_options' => collect($registry->all())->map(fn (Ruleset $r) => ['key' => $r->key, 'label' => $r->label])->values(),
        ]);
    }

    public function view(Tournament $tournament): Response|ResponseFactory
    {
        return inertia('Tournaments/View', $this->payloads->forView($tournament));
    }

    /**
     * Big-screen mode for a projector / TV at the venue: current round's pairings, scenario and clock,
     * rotating with the standings. Public, like the view page, and fed by the same props.
     */
    public function display(Tournament $tournament): Response|ResponseFactory
    {
        $props = $this->payloads->forView($tournament);
        unset($props['scheme_stats'], $props['allegiances'], $props['crew_lists']);

        return inertia('Tournaments/Display', $props);
    }

    /** The scoring desk: enter and correct results. Organizers and the event's scorekeepers. */
    public function score(Tournament $tournament): Response|ResponseFactory
    {
        $this->authorize('score', $tournament);

        return inertia('Tournaments/Score', $this->payloads->forScoring($tournament));
    }

    public function manage(Tournament $tournament): Response|ResponseFactory
    {
        $this->authorize('manage', $tournament);

        return inertia('Tournaments/Manage', $this->payloads->forManage($tournament));
    }

    public function create(Request $request): Response|ResponseFactory
    {
        $seasons = collect(PoolSeasonEnum::casesNewestFirst())->map(fn (PoolSeasonEnum $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        $encounterTypes = collect(EncounterTypeEnum::cases())->map(fn ($e) => [
            'value' => $e->value,
            'label' => $e->label(),
        ]);

        $userId = (int) Auth::id();

        // "Start from a previous event": prefill the format/scoring settings from one the user organizes.
        $template = null;
        $copyUuid = $request->query('copy');
        if (is_string($copyUuid) && $copyUuid !== '') {
            $source = Tournament::forOrganizer($userId)->where('uuid', $copyUuid)->first();
            if ($source) {
                /** @var PoolSeasonEnum $season */
                $season = $source->season;
                /** @var EncounterTypeEnum $encounterType */
                $encounterType = $source->encounter_type;
                /** @var \App\Enums\TournamentTiebreakerEnum $tiebreaker */
                $tiebreaker = $source->tiebreaker_mode;
                $template = [
                    'ruleset' => $source->rulesetDefinition()->key,
                    'name' => $source->name.' (copy)',
                    'description' => $source->description,
                    'location' => $source->location,
                    'encounter_size' => $source->encounter_size,
                    'encounter_type' => $encounterType->value,
                    'planned_rounds' => $source->planned_rounds,
                    'season' => $season->value,
                    'round_time_limit' => $source->round_time_limit,
                    'bye_tp' => $source->bye_tp,
                    'bye_diff' => $source->bye_diff,
                    'bye_vp' => $source->bye_vp,
                    'tiebreaker_mode' => $tiebreaker->value,
                    'variants' => $source->activeVariants(),
                    'allow_self_report' => (bool) $source->allow_self_report,
                    'team_size' => (int) $source->team_size,
                    'max_players' => $source->max_players,
                    'require_crew_lists' => (bool) $source->require_crew_lists,
                    'scoring_config' => $source->scoring_config,
                ];
            }
        }

        $registry = app(RulesetRegistry::class);

        return inertia('Tournaments/Create', [
            'rulesets' => array_values(array_map(fn (Ruleset $r) => $r->toArray(), $registry->all())),
            'default_ruleset' => $registry->default()->key,
            'seasons' => $seasons,
            'default_season' => PoolSeasonEnum::defaultSeason()->value,
            'encounter_types' => $encounterTypes,
            'template' => $template,
            // Plain arrays: serializing a column-restricted Tournament trips the `season_label` accessor.
            'previous_events' => Tournament::forOrganizer($userId)->latest('event_date')->limit(10)->get(['uuid', 'name', 'event_date'])
                ->map(fn (Tournament $t) => ['uuid' => $t->uuid, 'name' => $t->name, 'event_date' => $t->getRawOriginal('event_date')])
                ->values(),
        ]);
    }

    public function store(TournamentStoreRequest $request)
    {
        $validated = $request->validated();
        $validated = $this->normalizeCustomizations($validated, app(RulesetRegistry::class)->resolve($validated['ruleset'] ?? null));

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

    /**
     * Store only what's switched on / actually overridden: off variants and empty override arrays are dropped,
     * boolean variants become true and parameterised ones keep their number.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeCustomizations(array $validated, Ruleset $ruleset): array
    {
        if (array_key_exists('variants', $validated)) {
            $catalog = $ruleset->variantsByKey();
            $variants = [];
            foreach ((array) $validated['variants'] as $key => $value) {
                $param = $catalog[$key]['param'] ?? null;
                if ($param !== null) {
                    $variants[$key] = (int) $value;
                } elseif ($value) {
                    $variants[$key] = true;
                }
            }
            $validated['variants'] = $variants === [] ? null : $variants;
        }
        if (array_key_exists('scoring_config', $validated) && empty($validated['scoring_config'])) {
            $validated['scoring_config'] = null;
        }

        return $validated;
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
            'rounds.games.playerOne:id,display_name,faction,allegiance_id',
            'rounds.games.playerTwo:id,display_name,faction,allegiance_id',
            'rounds.games.playerOne.allegiance:id,name',
            'rounds.games.playerTwo.allegiance:id,name',
        ]);
        $declaresAllegiance = $tournament->rulesetDefinition()->declaration === 'allegiance';

        $filename = 'tournament-'.$tournament->uuid.'-'.now()->format('Ymd-His').'.csv';

        // Score components (beyond the VP total) are ruleset-defined, e.g. strategy + scheme for Malifaux.
        $componentKeys = array_values(array_filter($tournament->rulesetDefinition()->scoreFieldKeys(), fn (string $k) => $k !== 'vp'));

        return response()->streamDownload(function () use ($tournament, $componentKeys, $declaresAllegiance) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'round',
                'table',
                'player_one',
                'player_one_faction',
                'player_one_master',
                'player_one_vp',
                ...array_map(fn (string $k) => "player_one_{$k}_vp", $componentKeys),
                'player_two',
                'player_two_faction',
                'player_two_master',
                'player_two_vp',
                ...array_map(fn (string $k) => "player_two_{$k}_vp", $componentKeys),
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
                        $declaresAllegiance ? ($p1 ? $p1->allegiance?->name : null) : ($game->player_one_faction ?: ($p1 ? $p1->getRawOriginal('faction') : null)),
                        $game->player_one_title ?: $game->player_one_master,
                        $game->player_one_vp,
                        ...array_map(fn (string $k) => $game->breakdownFor('player_one')[$k] ?? null, $componentKeys),
                        $p2 ? $p2->display_name : null,
                        $declaresAllegiance ? ($p2 ? $p2->allegiance?->name : null) : ($game->player_two_faction ?: ($p2 ? $p2->getRawOriginal('faction') : null)),
                        $game->player_two_title ?: $game->player_two_master,
                        $game->player_two_vp,
                        ...array_map(fn (string $k) => $game->breakdownFor('player_two')[$k] ?? null, $componentKeys),
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

    public function update(TournamentUpdateRequest $request, Tournament $tournament)
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

        $validated = $this->normalizeCustomizations($request->validated(), $tournament->rulesetDefinition());

        if (isset($validated['team_size']) && (int) $validated['team_size'] !== (int) $tournament->team_size && $tournament->players()->exists()) {
            return response()->json(['error' => 'Remove the existing players before switching between individual and team play.'], 422);
        }

        if (isset($validated['max_players']) && $validated['max_players'] < $tournament->competitorCount()) {
            return response()->json(['error' => "Already {$tournament->competitorCount()} registered — the limit can't be lower than that."], 422);
        }

        $waiting = WaitlistPromotions::snapshot($tournament);
        $tournament->update($validated);
        WaitlistPromotions::notify($tournament, $waiting);

        $this->broadcastUpdate($tournament, 'settings_updated');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament)
    {
        // Deletion is destructive: creator-only (or super_admin via the policy's
        // before() hook). Invited organizers cannot delete.
        $this->authorize('delete', $tournament);

        // Creators can only delete drafts so they can't accidentally nuke an
        // event mid-run; super_admins (manage_tournaments perm) can delete in
        // any state for moderation cleanup.
        $isSuperAdmin = Auth::user()?->can(PermissionEnum::ManageTournaments->value) ?? false;
        if ($tournament->status !== TournamentStatusEnum::Draft && ! $isSuperAdmin) {
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

    public function updateStatus(TournamentUpdateStatusRequest $request, Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();

        $newStatus = TournamentStatusEnum::from($validated['status']);

        // Re-check the transition against the locked row: the early state may be stale.
        $error = DB::transaction(function () use ($tournament, $newStatus): ?string {
            $locked = Tournament::lockForUpdate()->findOrFail($tournament->id);

            if ($error = $this->stateMachine->canTransitionTournamentTo($locked, $newStatus)) {
                return $error;
            }

            $locked->update(['status' => $newStatus]);

            return null;
        });

        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $this->broadcastUpdate($tournament, 'status_changed');

        return response()->json(['success' => true]);
    }
}
