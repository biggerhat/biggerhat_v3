<?php

namespace App\Traits\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use App\Models\User;

/**
 * Shared "become a campaign member" logic — idempotent (firstOrCreate), so
 * it's safe to call from both the token-invitation accept flow
 * (CampaignInvitationController::accept()) and the public join-link flow
 * (CampaignController::joinPublic()). Deliberately carries no
 * invitation-specific side effects (e.g. marking a token accepted) — those
 * stay in the caller.
 */
trait AddsCampaignMember
{
    protected function addCampaignMember(Campaign $campaign, User $user): void
    {
        CampaignPlayer::firstOrCreate(
            ['campaign_id' => $campaign->id, 'user_id' => $user->id],
            ['role' => CampaignPlayerRoleEnum::Player],
        );

        // Auto-stub crew — Leader Builder + Starting Arsenal flows
        // (Phases 4-5) complete the rest of the row.
        CampaignCrew::firstOrCreate(
            ['campaign_id' => $campaign->id, 'user_id' => $user->id],
            ['name' => $user->name."'s Crew"],
        );
    }
}
