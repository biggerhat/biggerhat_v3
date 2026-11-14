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

        return inertia('Tournaments/Analytics', $insights->forUser((int) $request->user()->id, $range));
    }
}
