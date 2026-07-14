<?php

namespace App\Http\Controllers;

use App\Models\Campaign\CampaignInvitation;
use App\Models\Campaign\CampaignPlayer;
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

        // Optional: the Campaign invite picker passes its own campaign id so
        // already-joined/already-invited users don't show up as pickable
        // results (they'd just bounce off CampaignInvitationController's own
        // validation on submit otherwise). Opt-in only — the plain
        // friend-request search box passes nothing and is unaffected.
        $excludeCampaignId = $request->integer('exclude_campaign_id') ?: null;

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('name', 'like', $escaped.'%')
            ->when($excludeCampaignId, fn ($q) => $q
                ->whereNotIn('id', CampaignPlayer::query()->where('campaign_id', $excludeCampaignId)->pluck('user_id'))
                ->whereNotIn('id', CampaignInvitation::query()
                    ->where('campaign_id', $excludeCampaignId)
                    ->pending()
                    ->whereNotNull('user_id')
                    ->pluck('user_id')))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json(['users' => $users]);
    }
}
