<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\LuckyMiss;
use App\Services\CampaignRules;
use Illuminate\Http\Request;

/**
 * Renders a crew's Arsenal Sheet — the canonical public artifact described on
 * pg 56 of Index of the Untold. Two entry points:
 *
 *   /campaigns/{campaign}/crews/{crew}              — authed, requires membership
 *   /a/{share_code}                                  — public read-only, no auth
 *
 * The Vue layer is the same; the controller just toggles `is_owner` / `is_member`
 * flags so edit affordances stay hidden on the public path.
 */
class ArsenalSheetController extends Controller
{
    public function show(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        if ($crew->campaign_id !== $campaign->id) {
            abort(404);
        }

        $user = $request->user();
        $isMember = $user && (
            $user->hasRole('super_admin')
            || $campaign->players()->where('user_id', $user->id)->exists()
        );

        if (! $isMember) {
            abort(403);
        }

        return $this->render($campaign, $crew, isMember: true, isOwner: $user && $user->id === $crew->user_id);
    }

    /**
     * Public share — anyone with the share_code can view. Still gated by
     * `campaign.access` upstream so the page stays hidden while pre-release.
     */
    public function share(Request $request, string $shareCode)
    {
        $crew = CampaignCrew::query()->where('share_code', $shareCode)->firstOrFail();
        $campaign = $crew->campaign;

        return $this->render($campaign, $crew, isMember: false, isOwner: false);
    }

    private function render(Campaign $campaign, CampaignCrew $crew, bool $isMember, bool $isOwner)
    {
        // Single load picks up leader + totem via the dedicated relations on
        // CampaignCrew; both hit the composite (campaign_crew_id, flag, current)
        // index so two narrow queries replace the prior two open ones.
        $crew->load([
            'leader',
            'totem',
            'crewCardEffect.actions:id,name,type,stat,description',
            'crewCardEffect.abilities:id,name,description',
            'keywordOne:id,name,faction',
            'keywordTwo:id,name,faction',
            'arsenalModels' => fn ($q) => $q->active()->with([
                'character:id,display_name,cost,faction,station',
                'injuries.injury:id,name',
            ]),
        ]);

        // Resolve gained Lucky Miss ids to names for display.
        $luckyMissNames = LuckyMiss::query()->pluck('name', 'id');

        $leader = $crew->leader;
        $totem = $crew->totem;

        // Campaign Rating (pg 19): equipment + leader/totem advancements − injuries.
        // All three counters come from the consolidated post-refactor sources:
        // campaign_equipment, campaign_leader_advancements, campaign_arsenal_model_injuries.
        $equipmentCount = $crew->activeEquipmentCount();
        $advancementCount = $crew->activeLeaderAdvancementCount();
        $injuryCount = $crew->activeInjuryCount();
        $cr = CampaignRules::campaignRating($equipmentCount, $advancementCount, $injuryCount);

        return inertia('Campaigns/ArsenalSheet', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'length_weeks', 'current_week']),
            'crew' => array_merge(
                $crew->only(['id', 'share_code', 'name', 'faction', 'scrip', 'total_wins']),
                [
                    'keyword_one' => $crew->keywordOne,
                    'keyword_two' => $crew->keywordTwo,
                    'crew_card_effect' => $crew->crewCardEffect,
                    'arsenal_models' => $crew->arsenalModels->map(fn ($m) => [
                        'id' => $m->id,
                        'character_id' => $m->character_id,
                        'label' => $m->label,
                        'is_peon' => $m->is_peon,
                        'ignored_for_limits' => $m->ignored_for_limits,
                        'acquired_via' => $m->acquired_via,
                        'character' => $m->character,
                        'injuries' => $m->injuries->map(fn ($i) => $i->injury?->name)->filter()->values(),
                        'gained_characteristics' => $m->gained_characteristics ?? [],
                        'lucky_miss' => collect($m->gained_lucky_miss_ids ?? [])
                            ->map(fn ($id) => $luckyMissNames[$id] ?? null)
                            ->filter()
                            ->values(),
                    ]),
                ],
            ),
            'leader' => $leader,
            'totem' => $totem,
            'campaign_rating' => [
                'value' => $cr,
                'equipment_count' => $equipmentCount,
                'advancement_count' => $advancementCount,
                'injury_count' => $injuryCount,
            ],
            'view_mode' => [
                'is_member' => $isMember,
                'is_owner' => $isOwner,
                'share_url' => route('campaigns.crews.arsenal.share', $crew->share_code),
            ],
        ]);
    }
}
