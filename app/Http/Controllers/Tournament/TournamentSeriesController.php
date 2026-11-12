<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentSeriesAssignRequest;
use App\Http\Requests\Tournaments\TournamentSeriesRequest;
use App\Models\Tournament;
use App\Models\TournamentSeries;
use App\Tournaments\SeriesStandings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Leagues: a series groups an organizer's events and adds their finishing places into one public leaderboard.
 */
class TournamentSeriesController extends Controller
{
    public function __construct(private readonly SeriesStandings $standings) {}

    public function index(Request $request): Response|ResponseFactory
    {
        $q = trim((string) $request->query('q', ''));

        $series = TournamentSeries::query()
            ->with('creator:id,name')
            ->withCount(['tournaments', 'tournaments as finished_count' => fn ($query) => $query->where('status', TournamentStatusEnum::Completed->value)])
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($q, '\\%_').'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return inertia('Tournaments/Series/Index', [
            'series' => $series->through(fn (TournamentSeries $s) => [
                'uuid' => $s->uuid,
                'name' => $s->name,
                'description' => $s->description,
                'creator' => $s->creator->name,
                'events' => $s->tournaments_count,
                'finished' => $s->finished_count,
            ]),
            'filters' => ['q' => $q],
        ]);
    }

    public function show(TournamentSeries $series): Response|ResponseFactory
    {
        $series->load('creator:id,name');
        $board = $this->standings->compute($series);

        return inertia('Tournaments/Series/Show', [
            'series' => [
                'uuid' => $series->uuid,
                'name' => $series->name,
                'description' => $series->description,
                'creator' => $series->creator->name,
                'points_table' => $series->points_table,
                'participation_points' => $series->participation_points,
                'best_of' => $series->best_of,
            ],
            'events' => $series->tournaments()->get(['uuid', 'name', 'event_date', 'status'])->map(fn (Tournament $t) => [
                'uuid' => $t->uuid,
                'name' => $t->name,
                'event_date' => $t->event_date->toDateString(),
                'status' => $t->status->value,
            ])->values(),
            'standings' => $board['rows'],
            'scored_events' => $board['events'],
            'can_manage' => Auth::user()?->can('manage', $series) ?? false,
        ]);
    }

    public function create(): Response|ResponseFactory
    {
        $this->authorize('create', TournamentSeries::class);

        return inertia('Tournaments/Series/Form', ['series' => null, 'default_points' => TournamentSeries::DEFAULT_POINTS]);
    }

    public function store(TournamentSeriesRequest $request): RedirectResponse
    {
        $this->authorize('create', TournamentSeries::class);

        $series = TournamentSeries::create($this->attributes($request) + ['creator_id' => Auth::id()]);

        return redirect()->route('tournaments.series.show', $series);
    }

    public function edit(TournamentSeries $series): Response|ResponseFactory
    {
        $this->authorize('manage', $series);

        return inertia('Tournaments/Series/Form', [
            'series' => [
                'uuid' => $series->uuid,
                'name' => $series->name,
                'description' => $series->description,
                'points_table' => $series->points_table,
                'participation_points' => $series->participation_points,
                'best_of' => $series->best_of,
            ],
            'default_points' => TournamentSeries::DEFAULT_POINTS,
        ]);
    }

    public function update(TournamentSeriesRequest $request, TournamentSeries $series): RedirectResponse
    {
        $this->authorize('manage', $series);

        $series->update($this->attributes($request));

        return redirect()->route('tournaments.series.show', $series);
    }

    public function destroy(TournamentSeries $series): RedirectResponse
    {
        $this->authorize('manage', $series);

        // Its events stay, just no longer in a series (explicit, so it does not depend on the foreign key's ON DELETE).
        Tournament::where('series_id', $series->id)->update(['series_id' => null]);
        $series->delete();

        return redirect()->route('tournaments.series.index');
    }

    /** Put an event into one of the user's series (or take it out). */
    public function assign(TournamentSeriesAssignRequest $request, Tournament $tournament): JsonResponse
    {
        $uuid = $request->validated()['series'] ?? null;
        $series = $uuid ? TournamentSeries::where('uuid', $uuid)->firstOrFail() : null;

        if ($series !== null) {
            $this->authorize('manage', $series);
            if ($tournament->isTeamEvent()) {
                return response()->json(['error' => 'Team events cannot be part of a series — a series ranks individual players.'], 422);
            }
        }

        $tournament->update(['series_id' => $series?->id]);

        return response()->json(['success' => true]);
    }

    /** @return array<string, mixed> */
    private function attributes(TournamentSeriesRequest $request): array
    {
        $v = $request->validated();

        return [
            'name' => $v['name'],
            'description' => ($v['description'] ?? null) ?: null,
            'points_table' => array_map('intval', array_values($v['points'])),
            'participation_points' => (int) $v['participation_points'],
            'best_of' => $v['best_of'] ?? null,
        ];
    }
}
