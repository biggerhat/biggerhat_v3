<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Support\CampaignAccess;
use Illuminate\Http\Request;

/**
 * Public-facing teaser for M4E Campaign Mode. Lives OUTSIDE the
 * `campaign.access` middleware so unauthenticated visitors and authed users
 * without `use_campaign_mode` can see the coming-soon page. Users who DO
 * have access get redirected straight to `/campaigns`.
 *
 * Designed to anchor the pre-launch funnel: discovery → request access → full
 * UX when the Pennant flag flips on for open beta.
 */
class CampaignTeaserController extends Controller
{
    public function show(Request $request)
    {
        if (CampaignAccess::canUse($request->user())) {
            return redirect()->route('campaigns.index');
        }

        return inertia('Campaigns/Teaser');
    }
}
