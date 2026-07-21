<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\CampaignCrew;
use App\Support\Campaign\CombinedCrewCardEffects;
use Inertia\Response;

/**
 * Headless-Chrome capture target for App\Services\Campaign\CombinedCrewCardImageGenerator
 * — bare card face only, no page chrome. Public/unauthenticated like
 * CustomCharacterController::capture (same trust model: crew content, not a
 * user secret), hit only by the queue worker.
 */
class CrewCardCaptureController extends Controller
{
    /**
     * The combined per-crew card (starter effect + every held Tier-4 borrow,
     * pg 15-16 / 32 / 54) — see CombinedCrewCardEffects for the shared
     * builder and how restriction qualifiers are resolved.
     */
    public function combined(CampaignCrew $crew): Response
    {
        CombinedCrewCardEffects::eagerLoad($crew);

        return inertia('CardCreator/CaptureCombinedCrewCard', [
            'crewName' => $crew->name,
            'items' => CombinedCrewCardEffects::build($crew),
        ]);
    }
}
