<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Tournaments\Analytics\OrganizerInsights;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

/** Analytics across every event the signed-in user organizes. Each person only ever sees their own events. */
class TournamentAnalyticsController extends Controller
{
    public function __invoke(Request $request, OrganizerInsights $insights): Response|ResponseFactory
    {
        $range = (string) $request->query('range', 'all');

        $data = $insights->forUser((int) $request->user()->id, $range);

        return inertia('Tournaments/Analytics', $data + ['scope' => [
            'title' => 'Tournament analytics',
            'subtitle' => 'Across every event you organize',
            'back' => ['label' => 'Tournaments', 'href' => route('tournaments.index')],
            'csv' => route('tournaments.analytics_csv', ['range' => $data['range'] === 'all' ? null : $data['range']]),
        ]]);
    }
}
