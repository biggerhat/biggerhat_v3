<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentImportRequest;
use App\Tournaments\ResultsImport;
use App\Tournaments\Rulesets\Ruleset;
use App\Tournaments\Rulesets\RulesetRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-fill a finished event from a CSV of its games (the same shape as the tournament export): check the file first,
 * then create a completed tournament owned by the importer.
 */
class TournamentImportController extends Controller
{
    public function __construct(private readonly ResultsImport $import, private readonly RulesetRegistry $rulesets) {}

    public function create(): Response|ResponseFactory
    {
        return inertia('Tournaments/Import', [
            'rulesets' => array_values(array_map(fn (Ruleset $r) => $r->toArray(), $this->rulesets->all())),
            'default_ruleset' => $this->rulesets->resolve(null)->key,
            'seasons' => collect(PoolSeasonEnum::casesNewestFirst())->map(fn (PoolSeasonEnum $s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'default_season' => PoolSeasonEnum::defaultSeason()->value,
        ]);
    }

    /** Read the file without saving anything: counts, problems and warnings. */
    public function preview(TournamentImportRequest $request): JsonResponse
    {
        $plan = $this->import->parse($this->contents($request), $this->rulesets->resolve($request->validated()['ruleset']));

        return response()->json([
            'ok' => $plan['errors'] === [],
            'errors' => $plan['errors'],
            'warnings' => $plan['warnings'],
            'rounds' => count($plan['rounds']),
            'games' => $plan['game_count'],
            'players' => array_values(array_map(fn (array $p) => $p['name'], array_slice($plan['players'], 0, 60))),
            'player_count' => count($plan['players']),
        ]);
    }

    public function store(TournamentImportRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ruleset = $this->rulesets->resolve($validated['ruleset']);
        $plan = $this->import->parse($this->contents($request), $ruleset);
        if ($plan['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $plan['errors'][0]]);
        }

        $tournament = $this->import->create($request->user(), $ruleset, [
            'name' => $validated['name'],
            'event_date' => $validated['event_date'],
            'location' => $validated['location'] ?? null,
            'encounter_size' => (int) $validated['encounter_size'],
            'season' => $ruleset->usesSeason ? ($validated['season'] ?? null) : null,
        ], $plan);

        return redirect()->route('tournaments.view', $tournament)->with('success', 'Results imported.');
    }

    /** A small example file for the chosen format, so people can see the columns. */
    public function template(string $ruleset): StreamedResponse
    {
        $ruleset = $this->rulesets->resolve($ruleset);
        $parts = array_values(array_filter($ruleset->scoreFieldKeys(), fn (string $k) => $k !== 'vp'));
        $head = ['round', 'table', 'player_one', 'player_one_faction', 'player_one_vp', ...array_map(fn (string $k) => "player_one_{$k}_vp", $parts),
            'player_two', 'player_two_faction', 'player_two_vp', ...array_map(fn (string $k) => "player_two_{$k}_vp", $parts), 'result', 'is_bye', 'is_forfeit'];
        $example = fn (array $o) => array_map(fn ($c) => $o[$c] ?? '', $head);

        return response()->streamDownload(function () use ($head, $example, $parts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head, ',', '"', '');
            $a = ['round' => 1, 'table' => 1, 'player_one' => 'Alice', 'player_one_faction' => 'Arcanists', 'player_one_vp' => 8, 'player_two' => 'Bob', 'player_two_faction' => 'Guild', 'player_two_vp' => 5, 'result' => 'completed', 'is_bye' => 'false', 'is_forfeit' => 'false'];
            foreach ($parts as $i => $k) {
                $a["player_one_{$k}_vp"] = $i === 0 ? 5 : 3;
                $a["player_two_{$k}_vp"] = $i === 0 ? 3 : 2;
            }
            fputcsv($out, $example($a), ',', '"', '');
            fputcsv($out, $example(['round' => 1, 'player_one' => 'Carol', 'is_bye' => 'true']), ',', '"', '');
            fclose($out);
        }, 'tournament-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function contents(TournamentImportRequest $request): string
    {
        return (string) file_get_contents($request->file('file')->getRealPath());
    }
}
