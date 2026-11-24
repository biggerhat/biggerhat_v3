<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Http\Requests\Stats\AnalyticsFilterRequest;
use App\Models\User;
use App\Support\Stats\GameAnalytics;
use App\Support\Stats\PlayedGame;
use App\Support\Stats\PlayedGames;
use App\Tournaments\Analytics\InsightsCsv;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * In-depth statistics over every finished game a person played: through the Game Tracker, logged by hand, and tournament rounds. Public
 * like the stats page; what a visitor sees is limited the same way (another person's private logged games are never in it).
 */
class GameAnalyticsController extends Controller
{
    public function __construct(private readonly PlayedGames $played, private readonly GameAnalytics $analytics) {}

    public function show(AnalyticsFilterRequest $request, User $user): Response|ResponseFactory
    {
        $all = $this->all($request, $user);
        $filters = $request->filters();
        $games = $this->analytics->filter($all, $filters);

        return inertia('Profile/Analytics', [
            'user' => ['name' => $user->name, 'slug' => $user->slug],
            'is_own_profile' => $request->user()?->id === $user->id,
            'filters' => (object) $filters,
            'options' => $this->analytics->options($all),
            'faction_labels' => collect(FactionEnum::cases())->mapWithKeys(fn (FactionEnum $f) => [$f->value => $f->label()]),
            'total_games' => $all->count(),
            'analytics' => $this->analytics->compute($games),
            // the table of games is the same list the CSV downloads, newest first
            'games' => $games->take(500)->map(fn (PlayedGame $g) => $g->toRow())->values(),
            'games_truncated' => $games->count() > 500,
        ]);
    }

    public function csv(AnalyticsFilterRequest $request, User $user): StreamedResponse
    {
        $games = $this->analytics->filter($this->all($request, $user), $request->filters());
        $headers = ['Date', 'Source', 'Result', 'Faction', 'Master', 'Role', 'VP', 'Opponent', 'Opponent faction', 'Opponent master', 'Opponent VP', 'Strategy', 'Deployment', 'Size', 'Season', 'How played', 'Event / venue', 'Solo'];
        $rows = $games->map(fn (PlayedGame $g) => [
            $g->date, $g->source, $g->result, $g->faction, $g->master, $g->role, $g->vp, $g->oppName, $g->oppFaction, $g->oppMaster, $g->oppVp,
            $g->strategy, $g->deployment, $g->encounterSize, $g->season, $g->playMode, $g->venue, $g->isSolo ? 'yes' : 'no',
        ])->all();

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            InsightsCsv::write($out, ['Games' => [$headers, $rows]]);
            fclose($out);
        }, Str::slug($user->name ?: 'games').'-games-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return Collection<int, PlayedGame> */
    private function all(AnalyticsFilterRequest $request, User $user): Collection
    {
        return $this->played->for($user, $request->user()?->id === $user->id);
    }
}
