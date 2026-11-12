<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentDiscordRequest;
use App\Models\Tournament;
use App\Services\Discord\TournamentDiscord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Connects an event to a Discord channel webhook so BiggerHat can post its news there (see TournamentDiscord).
 */
class TournamentDiscordController extends Controller
{
    public function update(TournamentDiscordRequest $request, Tournament $tournament): JsonResponse
    {
        $url = trim((string) ($request->validated()['webhook_url'] ?? ''));

        $tournament->update(['discord_webhook_url' => $url === '' ? null : $url]);

        return response()->json(['success' => true, 'connected' => $url !== '']);
    }

    /** Post a test message now, so the organizer sees at once whether the webhook works. */
    public function test(Tournament $tournament, TournamentDiscord $discord): JsonResponse
    {
        Gate::authorize('manage', $tournament);

        if (! $tournament->discord_webhook_url) {
            return response()->json(['error' => 'Connect a Discord webhook first.'], 422);
        }
        if (! $discord->send($tournament, $discord->test($tournament))) {
            return response()->json(['error' => 'Discord did not accept the message — check that the webhook still exists.'], 422);
        }

        return response()->json(['success' => true]);
    }
}
