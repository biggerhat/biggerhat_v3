<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreLeaderAdvancementRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderAdvancementService;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;

/**
 * Log / remove a Leadership-Experience advancement against the crew's leader
 * straight from the Arsenal Sheet (pg 31). Advancements are also taken during
 * the Aftermath's Advance-Leader step; both go through LeaderAdvancementService
 * so the rules + record shape stay identical.
 */
class LeaderAdvancementController extends Controller
{
    use AuthorizesCampaignAccess;

    public function store(StoreLeaderAdvancementRequest $request, Campaign $campaign, CampaignCrew $crew, LeaderAdvancementService $service)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $leader = $this->currentLeader($crew);
        if (! $leader) {
            return redirect()->back()->withMessage('No active leader to advance — build one first.', null, MessageTypeEnum::error);
        }

        $data = $request->validated();
        $position = (int) $data['position_in_xp_track'];

        // The box must be earned (filled) and grant an advancement (numbered tier),
        // and not already hold one — pick a different box or remove it first.
        $box = collect($leader->xp_track ?? CustomCharacter::defaultXpTrack())->firstWhere('index', $position);
        if (! $box || empty($box['filled']) || ($box['tier'] ?? null) === null) {
            return redirect()->back()->withMessage('That experience box has not been earned yet, or grants no advancement.', null, MessageTypeEnum::error);
        }
        $alreadyTaken = CampaignLeaderAdvancement::query()
            ->where('custom_character_id', $leader->id)
            ->where('position_in_xp_track', $position)
            ->exists();
        if ($alreadyTaken) {
            return redirect()->back()->withMessage('That box already has an advancement — remove it first to change it.', null, MessageTypeEnum::error);
        }

        $rejection = $service->validate($leader, [$data]);
        if ($rejection !== null) {
            return redirect()->back()->withMessage($rejection, null, MessageTypeEnum::error);
        }

        // source_aftermath_id is null — this was logged directly, not via an aftermath.
        $service->create($leader, [$data], null);

        return redirect()->back()->withMessage('Advancement logged.');
    }

    public function destroy(Request $request, Campaign $campaign, CampaignCrew $crew, CampaignLeaderAdvancement $advancement, LeaderAdvancementService $service)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $leader = $this->currentLeader($crew);
        if (! $leader || $advancement->custom_character_id !== $leader->id) {
            abort(403);
        }

        $service->revertAdvancement($leader, $advancement, $crew);
        $advancement->delete();

        return redirect()->back()->withMessage('Advancement removed.');
    }

    private function currentLeader(CampaignCrew $crew): ?CustomCharacter
    {
        return CustomCharacter::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->first();
    }
}
