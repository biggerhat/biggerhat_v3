<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\CharacterStationEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Trigger;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search endpoints for the Leader Builder. Mirrors CardCreatorSearchController's
 * shape so the existing CardRenderer / type contracts work unchanged, but adds
 * the campaign-mode constraints:
 *
 *   - source character shares at least one keyword with the crew
 *   - source character is not a master or totem (cost-bearing models only)
 *   - action/ability stone_cost ≤ archetype cap (passed as ?max_cost)
 */
class LeaderSearchController extends Controller
{
    use AuthorizesCampaignAccess;

    /**
     * Constraint shared by the action/ability search filter and the eager-load:
     * a valid source is a cost-bearing model that shares a crew keyword and is
     * neither a master nor a totem (pg 17).
     *
     * @param  array<int, int>  $keywordIds
     */
    private function validSourceCharacterFilter(array $keywordIds): \Closure
    {
        return function ($q) use ($keywordIds) {
            $q->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
                ->whereNotIn('station', [CharacterStationEnum::Master->value])
                ->whereDoesntHave('keywords', fn ($k) => $k->where('name', 'like', '%Totem%'));
        };
    }

    public function actions(Request $request, Campaign $campaign, CampaignCrew $crew): JsonResponse
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $q = (string) $request->get('q', '');
        $maxCost = $request->integer('max_cost');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        if (empty($keywordIds)) {
            return response()->json([]);
        }

        $sourceFilter = $this->validSourceCharacterFilter($keywordIds);

        $actions = Action::query()
            ->where('name', 'LIKE', "%{$q}%")
            ->when($maxCost !== null, fn ($qq) => $qq->where(function ($n) use ($maxCost) {
                $n->whereNull('stone_cost')->orWhere('stone_cost', '<=', $maxCost);
            }))
            ->whereHas('characters', $sourceFilter)
            // Eager-load one valid source character so the picker can submit it;
            // the save-time validator re-verifies it (pg 17).
            ->with(['triggers', 'characters' => $sourceFilter])
            ->limit(25)
            ->orderBy('name')
            ->get();

        return response()->json($actions->map(fn (Action $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'is_signature' => (bool) $a->is_signature,
            'stone_cost' => $a->stone_cost ?? 0,
            'range' => $a->range,
            'range_type' => $a->range_type,
            'stat' => $a->stat,
            'stat_suits' => $a->stat_suits,
            'stat_modifier' => $a->stat_modifier,
            'resisted_by' => $a->resisted_by,
            'target_number' => $a->target_number,
            'target_suits' => $a->target_suits,
            'damage' => $a->damage,
            'description' => $a->description,
            'source_id' => $a->id,
            'source_character_id' => $a->characters->first()?->id,
            'triggers' => $a->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ])->values()->toArray(),
        ])->values());
    }

    public function abilities(Request $request, Campaign $campaign, CampaignCrew $crew): JsonResponse
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $q = (string) $request->get('q', '');
        $maxCost = $request->integer('max_cost');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        if (empty($keywordIds)) {
            return response()->json([]);
        }

        $sourceFilter = $this->validSourceCharacterFilter($keywordIds);

        $abilities = Ability::query()
            ->where('name', 'LIKE', "%{$q}%")
            // Abilities don't carry stone_cost directly the same way actions do
            // (the column on abilities is `costs_stone` boolean); when a cap is
            // supplied we treat any costs_stone=true ability as ≥1 and filter
            // out via cap if cap is 0. Otherwise unfiltered.
            ->when($maxCost === 0, fn ($qq) => $qq->where('costs_stone', false))
            ->whereHas('characters', $sourceFilter)
            ->with(['characters' => $sourceFilter])
            ->limit(25)
            ->orderBy('name')
            ->get();

        return response()->json($abilities->map(fn (Ability $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'suits' => $a->suits,
            'defensive_ability_type' => $a->defensive_ability_type,
            'costs_stone' => (bool) $a->costs_stone,
            'description' => $a->description,
            'source_id' => $a->id,
            'source_character_id' => $a->characters->first()?->id,
        ])->values());
    }
}
