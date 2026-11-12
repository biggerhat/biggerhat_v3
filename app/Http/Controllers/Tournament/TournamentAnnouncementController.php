<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentAnnouncementRequest;
use App\Jobs\PostTournamentToDiscord;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;

/**
 * The organizer's one-line notice for everyone watching the event (public page + display mode).
 */
class TournamentAnnouncementController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function update(TournamentAnnouncementRequest $request, Tournament $tournament): JsonResponse
    {
        $text = trim((string) ($request->validated()['announcement'] ?? ''));

        $tournament->update([
            'announcement' => $text === '' ? null : $text,
            'announced_at' => $text === '' ? null : now(),
        ]);

        if ($text !== '') {
            PostTournamentToDiscord::whenConnected($tournament, 'announcement', null, $text);
        }

        $this->broadcastUpdate($tournament, 'announcement');

        return response()->json(['success' => true, 'announcement' => $tournament->announcement]);
    }
}
