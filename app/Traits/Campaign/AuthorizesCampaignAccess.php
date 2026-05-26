<?php

namespace App\Traits\Campaign;

use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use Illuminate\Http\Request;

/**
 * Shared campaign authorization helpers. Consolidates the
 * ensureMember / ensureCrewOwner / ensureGamePlayer / ensureAftermathOwner
 * checks that were previously copy-pasted across 6 controllers — keeping the
 * super_admin bypass consistent and giving one place to evolve the rules.
 *
 * Each helper aborts with the appropriate HTTP status; controllers call them
 * at the top of each action and otherwise proceed normally.
 */
trait AuthorizesCampaignAccess
{
    /**
     * Caller is a campaign member (or super_admin). Used by routes that don't
     * carry a per-crew context (NewGame picker, weekly cycle, etc.).
     */
    protected function ensureCampaignMember(Request $request, Campaign $campaign): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $isMember = $user->hasRole('super_admin')
            || $campaign->players()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            abort(403);
        }
    }

    /**
     * Crew belongs to the campaign AND is owned by the caller. Used by every
     * per-crew flow (Leader Builder, Starting Arsenal, Weekly Hire, etc.).
     * 404 on cross-campaign references (hide existence), 403 on cross-user.
     */
    protected function ensureCrewOwner(Request $request, Campaign $campaign, CampaignCrew $crew): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($crew->campaign_id !== $campaign->id) {
            abort(404);
        }

        if ($crew->user_id !== $user->id && ! $user->hasRole('super_admin')) {
            abort(403);
        }
    }

    /**
     * Caller is a member of the wrapping campaign for a given CampaignGame.
     * Used by aftermath start/show — both crews' players can hit the show
     * page; per-crew mutations are gated by ensureAftermathOwner.
     */
    protected function ensureGameMember(Request $request, CampaignGame $campaignGame): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $isMember = $user->hasRole('super_admin')
            || $campaignGame->campaign->players()->where('user_id', $user->id)->exists();

        if (! $isMember) {
            abort(403);
        }
    }

    /**
     * Caller owns the crew this aftermath belongs to. Used by all per-aftermath
     * mutations (draw-hand, payday, barter, advance-leader, doctor, etc.).
     */
    protected function ensureAftermathOwner(Request $request, CampaignAftermath $aftermath): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $aftermath->loadMissing('crew');
        if ($user->id !== $aftermath->crew->user_id && ! $user->hasRole('super_admin')) {
            abort(403);
        }
    }
}
