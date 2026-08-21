<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\MessageTypeEnum;
use App\Events\CampaignCrewUpdated;
use App\Http\Controllers\Campaign\Concerns\BroadcastsCampaignEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreLeaderAdvancementRequest;
use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use App\Services\Campaign\LeaderAdvancementService;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Log / remove a Leadership-Experience advancement against the crew's leader
 * straight from the Arsenal Sheet (pg 31). Advancements are also taken during
 * the Aftermath's Advance-Leader step; both go through LeaderAdvancementService
 * so the rules + record shape stay identical.
 */
class LeaderAdvancementController extends Controller
{
    use AuthorizesCampaignAccess;
    use BroadcastsCampaignEvents;

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

        $this->broadcastToCampaign($campaign, new CampaignCrewUpdated($crew));

        return redirect()->back()->withMessage('Advancement logged.');
    }

    /**
     * Undoes EVERY advancement the leader has ever taken — the only way to
     * undo an advancement (QA: individual advancements aren't removable
     * one at a time). Leadership Experience boxes stay filled/earned; only
     * the picks reset, so the player re-takes them in order afterward.
     *
     * Reverts newest-first (by acquired_at, not position_in_xp_track — a
     * box's index doesn't reflect *when* it was taken, and an advancement
     * applied to the Totem can only have been taken after the Totem-
     * granting advancement itself, so undoing in true chronological reverse
     * order guarantees that dependency is torn down before its target is).
     * If a Totem advancement was ever taken, respeccing deletes the Totem
     * entirely, same as removing that one advancement always has.
     */
    public function respec(Request $request, Campaign $campaign, CampaignCrew $crew, LeaderAdvancementService $service)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $leader = $this->currentLeader($crew);
        if (! $leader) {
            return redirect()->back()->withMessage('No active leader to respec.', null, MessageTypeEnum::error);
        }

        $count = DB::transaction(function () use ($leader, $crew, $service) {
            $advancements = CampaignLeaderAdvancement::query()
                ->where('custom_character_id', $leader->id)
                ->orderByDesc('acquired_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            foreach ($advancements as $advancement) {
                $service->revertAdvancement($leader, $advancement, $crew);
                $advancement->delete();
            }

            return $advancements->count();
        });

        if ($count === 0) {
            return redirect()->back()->withMessage('No advancements to respec.');
        }

        GenerateLeaderCardImage::dispatch($leader->id)->afterCommit();

        $this->broadcastToCampaign($campaign, new CampaignCrewUpdated($crew));

        return redirect()->back()->withMessage(
            "Respecced — {$count} advancement(s) undone. Experience boxes stay earned; re-take advancements in order."
        );
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
