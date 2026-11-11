<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentDetailsRequest;
use App\Http\Requests\Tournaments\TournamentImageUploadRequest;
use App\Models\Tournament;
use App\Tournaments\RichText;
use Illuminate\Http\JsonResponse;

/**
 * The long-form event page: a rich-text document the organizer writes (rules, venue, schedule, prizes) and the images
 * placed in it. The document is rebuilt through the RichText allow-list before it is stored.
 */
class TournamentDetailsController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function update(TournamentDetailsRequest $request, Tournament $tournament): JsonResponse
    {
        $details = RichText::sanitize($request->validated()['details'] ?? null);

        $tournament->update(['details' => $details]);

        $this->broadcastUpdate($tournament, 'details_updated');

        return response()->json(['success' => true, 'details' => $details]);
    }

    public function uploadImage(TournamentImageUploadRequest $request, Tournament $tournament): JsonResponse
    {
        $path = $request->file('image')->store("tournaments/{$tournament->id}/content", 'public');

        return response()->json(['url' => '/storage/'.$path]);
    }
}
