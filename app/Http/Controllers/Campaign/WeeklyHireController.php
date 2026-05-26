<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreWeeklyHireRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Character;
use App\Services\CampaignRules;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-player weekly New Hires flow (pg 18). Mandatory: every player must add
 * at least one model when a new week starts. Costs follow
 * CampaignRules::newHireScripCost — first-of-week is 5 scrip cheaper;
 * out-of-keyword in-faction is +1.
 *
 * Unlike StartingArsenal (one-shot, replaces), weekly hires accumulate via
 * `acquired_week`. The "is this my first hire of week X" check is just:
 * count(arsenal_models where acquired_week = current_week && acquired_via = 'hire') == 0.
 */
class WeeklyHireController extends Controller
{
    use AuthorizesCampaignAccess;

    public function edit(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $alreadyHiredThisWeek = $this->countHiresThisWeek($crew, $campaign->current_week);

        return inertia('Campaigns/WeeklyHire', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'current_week', 'length_weeks']),
            'crew' => $crew->only(['id', 'share_code', 'name', 'faction', 'scrip', 'keyword_1_id', 'keyword_2_id']),
            'hireable' => fn () => $this->hireableModels($crew),
            'already_hired_this_week' => $alreadyHiredThisWeek,
            'locked' => $campaign->status !== CampaignStatusEnum::Active,
        ]);
    }

    public function update(StoreWeeklyHireRequest $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        if ($campaign->status !== CampaignStatusEnum::Active) {
            return redirect()->back()->withMessage(
                'Weekly hiring is only available while the campaign is active.',
                null,
                MessageTypeEnum::error,
            );
        }

        $data = $request->validated();
        $hires = $data['hires'];

        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        $hireableMap = $this->buildHireabilityMap($crew);

        $alreadyHired = $this->countHiresThisWeek($crew, $campaign->current_week);
        $totalCost = 0;
        $invalid = [];
        $costDetail = [];

        foreach ($hires as $i => $h) {
            $charId = (int) $h['character_id'];
            $meta = $hireableMap[$charId] ?? null;
            if (! $meta) {
                $invalid[] = $charId;

                continue;
            }
            $cost = CampaignRules::newHireScripCost(
                modelCost: $meta['cost'],
                outOfKeywordInFaction: $meta['out_of_keyword'],
                isFirstHireOfWeek: ($alreadyHired + $i) === 0,
            );
            $costDetail[] = ['character_id' => $charId, 'cost' => $cost, 'character_name' => $meta['name']];
            $totalCost += $cost;
        }

        if (! empty($invalid)) {
            return redirect()->back()->withMessage(
                'One or more models are not legally hireable.',
                null,
                MessageTypeEnum::error,
            );
        }

        if ($totalCost > $crew->scrip) {
            return redirect()->back()->withMessage(
                "Not enough scrip — needs {$totalCost}, have {$crew->scrip}.",
                null,
                MessageTypeEnum::error,
            );
        }

        DB::transaction(function () use ($crew, $campaign, $hires, $totalCost, $hireableMap, $keywordIds) {
            foreach ($hires as $h) {
                $charId = (int) $h['character_id'];
                $meta = $hireableMap[$charId];
                $character = Character::query()->whereKey($charId)->select(['id', 'station'])->first();

                // The other-keyword grant only applies to in-keyword models
                // (pg 15). For weekly out-of-keyword in-faction hires we don't
                // grant a second keyword.
                $grantedKeyword = null;
                if (! $meta['out_of_keyword'] && count($keywordIds) === 2) {
                    // Grant whichever of the crew's keywords the model didn't
                    // already have (encoded in $meta['has_keyword_id']).
                    $grantedKeyword = array_values(array_diff($keywordIds, [$meta['has_keyword_id']]))[0] ?? null;
                }

                CampaignArsenalModel::create([
                    'campaign_crew_id' => $crew->id,
                    'character_id' => $charId,
                    'label' => $h['label'] ?? null,
                    'is_peon' => $character?->station === CharacterStationEnum::Peon,
                    'acquired_week' => $campaign->current_week,
                    'acquired_via' => 'hire',
                    'granted_keyword_id' => $grantedKeyword,
                ]);
            }

            $crew->decrement('scrip', $totalCost);
        });

        return redirect()->route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code])
            ->withMessage('Hired '.count($hires)." model(s) for {$totalCost} scrip.");
    }

    private function countHiresThisWeek(CampaignCrew $crew, int $week): int
    {
        return CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('acquired_via', 'hire')
            ->where('acquired_week', $week)
            ->count();
    }

    /**
     * Build a fast lookup keyed by character_id with cost + flags. Avoids
     * one query per hire when validating + writing.
     *
     * @return array<int, array{cost: int, out_of_keyword: bool, has_keyword_id: int|null, name: string}>
     */
    private function buildHireabilityMap(CampaignCrew $crew): array
    {
        $models = $this->hireableModels($crew);
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        $map = [];

        foreach ($models as $m) {
            $inKeywordId = $m->keywords->whereIn('id', $keywordIds)->first()?->id;
            $map[$m->id] = [
                'cost' => (int) ($m->cost ?? 0),
                'out_of_keyword' => $inKeywordId === null,
                'has_keyword_id' => $inKeywordId,
                'name' => $m->display_name,
            ];
        }

        return $map;
    }

    /**
     * Weekly hire pool — in-keyword OR Versatile OR (any model in declared
     * faction, treated as out-of-keyword surcharge). Excludes masters.
     */
    private function hireableModels(CampaignCrew $crew)
    {
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);

        $query = Character::query()
            ->standard()
            ->whereNotIn('station', [CharacterStationEnum::Master->value])
            ->whereNotNull('cost')
            ->where('cost', '>', 0)
            ->when($crew->faction !== null, function ($q) use ($keywordIds, $crew) {
                $q->where(function ($q1) use ($keywordIds, $crew) {
                    if (! empty($keywordIds)) {
                        $q1->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds));
                    }
                    // Any other model in the declared faction is allowed (pg 18 +1 surcharge).
                    $q1->orWhere('faction', $crew->faction->value);
                });
            });

        // Stay Dead optional rule (pg 146): unique models that were previously
        // annihilated in this crew can never be re-hired.
        $stayDead = (bool) (($crew->campaign->optional_rules ?? [])['stay_dead'] ?? false);
        if ($stayDead) {
            $bannedIds = CampaignArsenalModel::query()
                ->where('campaign_crew_id', $crew->id)
                ->whereNotNull('annihilated_at')
                ->whereHas('character.characteristics', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['unique']))
                ->pluck('character_id')
                ->toArray();
            if (! empty($bannedIds)) {
                $query->whereNotIn('id', $bannedIds);
            }
        }

        return $query
            ->with(['keywords:id,name', 'characteristics:id,name'])
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'cost', 'faction', 'station']);
    }
}
