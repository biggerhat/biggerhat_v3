<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\MessageTypeEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreStartingArsenalRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
use App\Models\Upgrade;
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
            'crew' => $crew->only(['id', 'share_code', 'name', 'faction', 'keyword_1_id', 'keyword_2_id', 'scrip', 'crew_card_effect_id', 'crew_card_choice']),
            'arsenal' => $arsenal,
            'hireable' => fn () => $this->hireableModels($crew),
            'crew_card_effects' => fn () => CampaignCrewCard::query()
                ->with([
                    'actions:id,name,type,stat,stat_suits,stat_modifier,range,range_type,description',
                    'abilities:id,name,suits,defensive_ability_type,costs_stone,description',
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'description as body', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice']),
            // Constrained pool for crew cards that require a token/marker/upgrade
            // choice (pg 17): items listed on a crew card belonging to a master
            // sharing either of the crew's keywords.
            'crew_card_choice_options' => fn () => $this->crewCardChoiceOptions($crew),
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

        // Starting Anew (pg 36): crews starting fresh keep their existing scrip
        // bonus and spend from it rather than the standard 25 ss budget.
        $isStartingAnew = $crew->starting_anew_at !== null;

        if (! $isStartingAnew && $totalCost > self::STARTING_BUDGET_SS) {
            return redirect()->back()->withMessage(
                'Starting arsenal cost '.$totalCost.' ss exceeds the '.self::STARTING_BUDGET_SS.' ss budget.',
                null,
                MessageTypeEnum::error,
            );
        }

        // Starting Anew: leftover is whatever scrip remains after the hire cost
        // (no max cap since the crew's bonus may legitimately exceed 3). Standard
        // start: leftover is capped at 3 per pg 15.
        $leftoverScrip = $isStartingAnew
            ? max(0, $crew->scrip - $totalCost)
            : min(self::MAX_LEFTOVER_SCRIP, self::STARTING_BUDGET_SS - $totalCost);

        // Crew cards that require a token/marker/upgrade choice (pg 17) must
        // pick one of the constrained options; cards that don't store null.
        $crewCard = CampaignCrewCard::find($data['crew_card_effect_id']);
        $requiredType = match (true) {
            (bool) $crewCard?->requires_token_choice => 'token',
            (bool) $crewCard?->requires_marker_choice => 'marker',
            (bool) $crewCard?->requires_upgrade_type_choice => 'upgrade',
            default => null,
        };
        $crewCardChoice = null;
        if ($requiredType !== null) {
            $options = $this->crewCardChoiceOptions($crew)[$requiredType.'s'];
            $picked = collect($options)->firstWhere('id', $data['crew_card_choice']['id'] ?? null);
            if (! $picked) {
                return redirect()->back()->withMessage(
                    'This crew card requires choosing a '.$requiredType.' from a crew card of a master sharing your keywords.',
                    null,
                    MessageTypeEnum::error,
                );
            }
            $crewCardChoice = ['type' => $requiredType, 'id' => $picked['id'], 'name' => $picked['name']];
        }

        DB::transaction(function () use ($crew, $hires, $data, $leftoverScrip, $crewCardChoice) {
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
                'crew_card_choice' => $crewCardChoice,
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
            // Pg 15: the Leader IS the master, built separately. Pg 52: Totems
            // only enter the arsenal via a Tier-3 Totem Advancement. Neither
            // station should appear in the starting-arsenal hireable pool.
            // Masters filter on the station column directly; totems are
            // identified by being referenced as some master's `has_totem_id`.
            // "Not a master" — Enforcers/Henchmen/Uniques carry a NULL station
            // (henchman/enforcer are characteristics, not stations), and a plain
            // whereNotIn would drop NULLs since `NULL NOT IN (...)` is not true.
            ->where(fn ($q) => $q->whereNull('station')->orWhere('station', '!=', CharacterStationEnum::Master->value))
            ->whereDoesntHave('isTotemFor')
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

    /**
     * Options for a crew-card choice (pg 17-18) — everything listed on a crew
     * card (a crew-domain upgrade) belonging to a master sharing one of the
     * crew's keywords: its tokens, its markers, and the upgrade *types* it
     * carries (pg 18: "an upgrade type listed on a … crew card"). Token/marker
     * ids are ints; an upgrade-type id is its enum value (a string).
     *
     * @return array{tokens: list<array{id:int,name:string}>, markers: list<array{id:int,name:string}>, upgrades: list<array{id:string,name:string}>}
     */
    private function crewCardChoiceOptions(CampaignCrew $crew): array
    {
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        if (empty($keywordIds)) {
            return ['tokens' => [], 'markers' => [], 'upgrades' => []];
        }

        $crewCards = Upgrade::query()
            ->forCrews()
            ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
            ->with(['tokens:id,name', 'markers:id,name'])
            ->get(['id', 'name', 'type']);

        $shape = fn ($row) => ['id' => $row->id, 'name' => $row->name];

        return [
            'tokens' => $crewCards->flatMap->tokens->unique('id')->sortBy('name')->map($shape)->values()->all(),
            'markers' => $crewCards->flatMap->markers->unique('id')->sortBy('name')->map($shape)->values()->all(),
            // Upgrade *types* carried by those crew cards (not the cards
            // themselves) — keyed by the enum value so the pick is a real type.
            'upgrades' => $crewCards->pluck('type')->filter()->unique()
                ->map(fn (UpgradeTypeEnum $t) => ['id' => $t->value, 'name' => $t->label()])
                ->sortBy('name')->values()->all(),
        ];
    }
}
