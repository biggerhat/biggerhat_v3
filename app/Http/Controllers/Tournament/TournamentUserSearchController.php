<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentUserSearchController extends Controller
{
    /**
     * Lookup BiggerHat users by name. Used by the "Add Organizer" and
     * "Link account to player" dialogs in the manage UI.
     */
    public function __invoke(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        // Escape LIKE wildcards so underscores/percent signs are literal.
        // Prefix match keeps the query index-friendly.
        $escaped = addcslashes($q, '\\%_');

        $users = User::query()
            ->where('name', 'like', $escaped.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json(['users' => $users]);
    }
}
