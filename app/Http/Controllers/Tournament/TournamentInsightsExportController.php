<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentSeries;
use App\Services\TournamentStandingsService;
use App\Tournaments\Analytics\EventInsights;
use App\Tournaments\Analytics\InsightsCsv;
use App\Tournaments\Analytics\OrganizerInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads of the analytics screens, for organizers who want the numbers in a spreadsheet. */
class TournamentInsightsExportController extends Controller
{
    public function event(Tournament $tournament, EventInsights $insights, TournamentStandingsService $standings): StreamedResponse
    {
        $this->authorize('manage', $tournament);

        $sections = InsightsCsv::event($insights->forEvent($tournament, $standings->compute($tournament)));

        return $this->download(Str::slug($tournament->name).'-insights', $sections);
    }

    public function organizer(Request $request, OrganizerInsights $insights): StreamedResponse
    {
        $data = $insights->forUser((int) $request->user()->id, (string) $request->query('range', 'all'));

        return $this->download('tournament-analytics', InsightsCsv::overview($data));
    }

    public function series(TournamentSeries $series, OrganizerInsights $insights): StreamedResponse
    {
        $this->authorize('manage', $series);

        return $this->download(Str::slug($series->name).'-analytics', InsightsCsv::overview($insights->forSeries($series)));
    }

    /** @param  array<string, array{0: list<string>, 1: list<list<mixed>>}>  $sections */
    private function download(string $name, array $sections): StreamedResponse
    {
        return response()->streamDownload(function () use ($sections) {
            $out = fopen('php://output', 'w');
            InsightsCsv::write($out, $sections);
            fclose($out);
        }, ($name ?: 'analytics').'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
