<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreStartingArsenalRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CrewCardEffect;
use App\Models\Character;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One-shot wizard for setting up a fresh crew's 25-ss starting arsenal
 * (rulebook pg 15). Locked once the campaign starts — the crew is then
 * stuck with whatever rows it has (further growth happens via the weekly
 * New Hires step in Phase 7).
 */
class StartingArsenalController extends Controller
{
    use AuthorizesCampaignAccess;

    private const STARTING_BUDGET_SS = 25;

    private const MAX_LEFTOVER_SCRIP = 3;

    public function edit(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $arsenal = CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->whereNull('annihilated_at')
            ->with('character:id,display_name,cost,faction,station')
            ->get();

        return inertia('Campaigns/StartingArsenal', [
            'campaign' => $campaign->only(['id', 'name', 'status']),
            'crew' => $crew->only(['id', 'share_code', 'name', 'faction', 'keyword_1_id', 'keyword_2_id', 'scrip', 'crew_card_effect_id']),
            'arsenal' => $arsenal,
            'hireable' => fn () => $this->hireableModels($crew),
            'crew_card_effects' => fn () => CrewCardEffect::orderBy('name')
                ->get(['id', 'name', 'body', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice']),
            'starting_budget_ss' => self::STARTING_BUDGET_SS,
            'max_leftover_scrip' => self::MAX_LEFTOVER_SCRIP,
            'locked' => $campaign->status !== CampaignStatusEnum::Planning,
        ]);
    }

    public function update(StoreStartingArsenalRequest $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        if ($campaign->status !== CampaignStatusEnum::Planning) {
            return redirect()->back()->withMessage(
                'Starting arsenal is locked once the campaign is active.',
                null,
                MessageTypeEnum::error,
            );
        }

        $data = $request->validated();
        $hires = $data['hires'] ?? [];

        // Validate hireability + total cost server-side. Eligibility:
        //   - in-keyword (model.keywords intersects crew.keywords)
        //   - OR versatile in declared faction
        //   - NOT a master or totem
        $hireableIds = $this->hireableModelIds($crew);

        $totalCost = 0;
        $invalid = [];
        $characterCostById = [];

        foreach ($hires as $i => $h) {
            $charId = (int) $h['character_id'];
            if (! in_array($charId, $hireableIds, true)) {
                $invalid[] = $charId;

                continue;
            }
            $cost = $characterCostById[$charId] ?? Character::query()->whereKey($charId)->value('cost') ?? 0;
            $characterCostById[$charId] = $cost;
            $totalCost += (int) $cost;
        }

        if (! empty($invalid)) {
            return redirect()->back()->withMessage(
                'One or more chosen models are not hireable into this crew.',
                null,
                MessageTypeEnum::error,
            );
        }

        if ($totalCost > self::STARTING_BUDGET_SS) {
            return redirect()->back()->withMessage(
                'Starting arsenal cost '.$totalCost.' ss exceeds the '.self::STARTING_BUDGET_SS.' ss budget.',
                null,
                MessageTypeEnum::error,
            );
        }

        $leftoverScrip = min(self::MAX_LEFTOVER_SCRIP, self::STARTING_BUDGET_SS - $totalCost);

        DB::transaction(function () use ($crew, $hires, $data, $leftoverScrip) {
            // One-shot semantics: wipe + re-create. Players are free to
            // re-do their starting arsenal during planning. Annihilated/
            // removed rows aren't touched (annihilated_at + removed_at flags).
            $crew->arsenalModels()->where('acquired_via', 'hire')->whereNull('annihilated_at')->whereNull('removed_at')->delete();

            foreach ($hires as $h) {
                $character = Character::query()->whereKey((int) $h['character_id'])->select(['id', 'station'])->first();
                CampaignArsenalModel::create([
                    'campaign_crew_id' => $crew->id,
                    'character_id' => $h['character_id'],
                    'label' => $h['label'] ?? null,
                    'is_peon' => $character?->station === CharacterStationEnum::Peon,
                    'acquired_week' => 1,
                    'acquired_via' => 'hire',
                    // The non-chosen-keyword bonus from the model's keywords
                    // (pg 15 "permanently gains the player's other chosen keyword")
                    // is captured at hire time. UI may not yet ask which side;
                    // we leave it null for now.
                    'granted_keyword_id' => null,
                ]);
            }

            $crew->update([
                'scrip' => $leftoverScrip,
                'crew_card_effect_id' => $data['crew_card_effect_id'],
            ]);
        });

        return redirect()->route('campaigns.crews.starting-arsenal.edit', [$campaign, $crew])
            ->withMessage("Starting arsenal saved ({$totalCost} ss spent, {$leftoverScrip} scrip leftover).");
    }

    /**
     * @return array<int>
     */
    private function hireableModelIds(CampaignCrew $crew): array
    {
        return $this->hireableModelsQuery($crew)->pluck('id')->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Character>
     */
    private function hireableModels(CampaignCrew $crew)
    {
        return $this->hireableModelsQuery($crew)
            ->with(['keywords:id,name', 'characteristics:id,name'])
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'cost', 'faction', 'station']);
    }

    private function hireableModelsQuery(CampaignCrew $crew)
    {
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);

        return Character::query()
            // Standard scope filters out non-standard mode content, but campaign
            // hires draw from standard characters (the M4E catalog).
            ->standard()
            ->whereNotIn('station', [CharacterStationEnum::Master->value])
            ->whereNotNull('cost')
            ->where('cost', '>', 0)
            ->when(! empty($keywordIds) && $crew->faction !== null, function ($q) use ($keywordIds, $crew) {
                $q->where(function ($q1) use ($keywordIds, $crew) {
                    // In-keyword
                    $q1->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
                        // OR Versatile in declared faction
                        ->orWhere(function ($q2) use ($crew) {
                            $q2->where('faction', $crew->faction->value)
                                ->whereHas('characteristics', fn ($c) => $c->whereRaw('LOWER(name) = ?', ['versatile']));
                        });
                });
            });
    }
}
