<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lookup BiggerHat users by name. Used by the Campaign "invite an existing
 * player" picker and the friend-request search box. Gated by auth only —
 * there's no natural resource to scope an authorize() check against (unlike
 * TournamentUserSearchController::__invoke(), which mirrors this but scopes
 * to 'manage' on a specific Tournament) — this is intentional, not a gap:
 * no user-discoverability opt-out exists anywhere in this app yet.
 */
class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        // Escape LIKE wildcards so underscores/percent signs are literal.
        // Prefix match keeps the query index-friendly.
        $escaped = addcslashes($q, '\\%_');

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('name', 'like', $escaped.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json(['users' => $users]);
    }
}
