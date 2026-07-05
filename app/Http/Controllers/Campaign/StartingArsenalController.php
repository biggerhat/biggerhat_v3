<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\MessageTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreStartingArsenalRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Character;
use App\Models\CustomUpgrade;
use App\Models\Upgrade;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use App\Traits\Campaign\HiresTitledModelGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-shot wizard for setting up a fresh crew's 25-ss starting arsenal
 * (rulebook pg 15). Locked once the campaign starts — the crew is then
 * stuck with whatever rows it has (further growth happens via the weekly
 * New Hires step in Phase 7).
 */
class StartingArsenalController extends Controller
{
    use AuthorizesCampaignAccess;
    use HiresTitledModelGroup;

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
                    'actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                    'abilities',
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'description as body', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice'])
                ->each(fn ($card) => $card->actions->each(
                    fn ($a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
                )),
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
        $hireabilityMap = $this->buildHireabilityMap($crew);

        $totalCost = 0;
        $invalid = [];
        $uniqueCharIdsSeen = [];

        foreach ($hires as $h) {
            $charId = (int) $h['character_id'];
            $meta = $hireabilityMap[$charId] ?? null;
            if (! $meta) {
                $invalid[] = $charId;

                continue;
            }
            // Unique models may only appear once in the arsenal.
            if ($meta['is_unique']) {
                if (in_array($charId, $uniqueCharIdsSeen, true)) {
                    return redirect()->back()->withMessage(
                        'Unique models may only be hired once.',
                        null,
                        MessageTypeEnum::error,
                    );
                }
                $uniqueCharIdsSeen[] = $charId;
            }
            // OOK non-versatile models cost +1 ss (same rule as weekly hires).
            $totalCost += $meta['cost'] + ($meta['is_ook'] ? 1 : 0);
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

        $userId = $request->user()->id;
        $crewCardName = trim((string) ($data['crew_card_name'] ?? ''));

        DB::transaction(function () use ($crew, $hires, $data, $leftoverScrip, $crewCardChoice, $userId, $crewCardName) {
            // One-shot semantics: wipe + re-create. Players are free to
            // re-do their starting arsenal during planning. Annihilated/
            // removed rows aren't touched (annihilated_at + removed_at flags).
            $crew->arsenalModels()->where('acquired_via', 'hire')->whereNull('annihilated_at')->whereNull('removed_at')->delete();

            foreach ($hires as $h) {
                $character = Character::query()->whereKey((int) $h['character_id'])->select(['id', 'station', 'title_group_key'])->first();
                $arsenalModel = CampaignArsenalModel::create([
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

                // Titled models (pg 18): hiring one titled version adds the rest.
                if ($character !== null) {
                    $this->addTitledSiblings($arsenalModel, $character, $crew->id, 1);
                }
            }

            $crew->update([
                'scrip' => $leftoverScrip,
                'crew_card_effect_id' => $data['crew_card_effect_id'],
                'crew_card_choice' => $crewCardChoice,
            ]);

            // Save the chosen crew card to the owner's Card Creator (pg: the
            // master card already lives there as a CustomCharacter). Only when
            // the player named it on save.
            if ($crewCardName !== '') {
                $this->saveCrewCardToCardCreator($userId, (int) $data['crew_card_effect_id'], $crewCardName, $crew->faction?->value);
            }
        });

        return redirect()->route('campaigns.crews.starting-arsenal.edit', [$campaign, $crew])
            ->withMessage("Starting arsenal saved ({$totalCost} ss spent, {$leftoverScrip} scrip leftover).");
    }

    /**
     * Save the selected crew card to the owner's Card Creator as a crew-domain
     * CustomUpgrade (the master card already lives there as a CustomCharacter).
     * Re-saving with the same name updates the card instead of duplicating it.
     */
    private function saveCrewCardToCardCreator(int $userId, int $crewCardId, string $name, ?string $faction): void
    {
        $crewCard = CampaignCrewCard::query()
            ->with([
                'actions:id,name,type,stat,stat_suits,range,range_type,description',
                'abilities:id,name,suits,defensive_ability_type,costs_stone,description',
            ])
            ->find($crewCardId);
        if (! $crewCard) {
            return;
        }

        $blocks = [];
        if (! empty($crewCard->description)) {
            $blocks[] = ['type' => 'text', 'text' => $crewCard->description];
        }
        foreach ($crewCard->abilities as $a) {
            $blocks[] = ['type' => 'ability', 'data' => [
                'name' => $a->name,
                'description' => $a->description,
                'suits' => $a->suits,
                'defensive_ability_type' => $a->defensive_ability_type,
                'costs_stone' => (bool) $a->costs_stone,
            ]];
        }
        foreach ($crewCard->actions as $ac) {
            $blocks[] = ['type' => 'action', 'data' => [
                'name' => $ac->name,
                'type' => $ac->type,
                'stat' => $ac->stat,
                'stat_suits' => $ac->stat_suits,
                'range' => $ac->range,
                'range_type' => $ac->range_type,
                'description' => $ac->description,
            ]];
        }

        $existing = CustomUpgrade::query()
            ->where('user_id', $userId)
            ->where('name', $name)
            ->where('domain', UpgradeDomainTypeEnum::Crew->value)
            ->first();

        if ($existing) {
            $existing->update(['display_name' => $name, 'faction' => $faction, 'content_blocks' => $blocks]);

            return;
        }

        CustomUpgrade::create([
            'user_id' => $userId,
            'name' => $name,
            'display_name' => $name,
            'slug' => $this->uniqueUpgradeSlug($userId, $name),
            'domain' => UpgradeDomainTypeEnum::Crew->value,
            'faction' => $faction,
            'content_blocks' => $blocks,
        ]);
    }

    /** Per-user unique slug for a saved crew-card upgrade. */
    private function uniqueUpgradeSlug(int $userId, string $name): string
    {
        $base = Str::slug($name) ?: 'crew-card';
        $slug = $base;
        $i = 1;
        while (CustomUpgrade::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Build a fast lookup by character_id for use in validation.
     *
     * @return array<int, array{cost: int, is_ook: bool, is_unique: bool}>
     */
    private function buildHireabilityMap(CampaignCrew $crew): array
    {
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        $models = $this->hireableModelsQuery($crew)
            ->with(['keywords:id', 'characteristics:id,name'])
            ->get(['id', 'cost']);

        $map = [];
        foreach ($models as $m) {
            $inKeyword = $keywordIds && $m->keywords->whereIn('id', $keywordIds)->isNotEmpty();
            $isVersatile = $m->characteristics->contains(fn ($c) => strtolower($c->name) === 'versatile');
            $isUnique = $m->characteristics->contains(fn ($c) => strtolower($c->name) === 'unique');
            $map[$m->id] = [
                'cost' => (int) ($m->cost ?? 0),
                'is_ook' => ! $inKeyword && ! $isVersatile,
                'is_unique' => $isUnique,
            ];
        }

        return $map;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Character>
     */
    private function hireableModels(CampaignCrew $crew)
    {
        return $this->hireableModelsQuery($crew)
            ->with([
                'keywords:id,name',
                'characteristics:id,name',
                'miniatures' => fn ($q) => $q->select(['id', 'character_id', 'display_name', 'front_image']),
            ])
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'slug', 'cost', 'faction', 'station']);
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
                        // OR any model in the declared faction (out-of-keyword, no ss surcharge at starting arsenal)
                        ->orWhere('faction', $crew->faction->value);
                });
            });
    }

    /**
     * Options for a crew-card choice (pg 17-18).
     *
     * Tokens/markers: items listed on crew-card upgrades belonging to a master
     * sharing the crew's keywords.
     *
     * Upgrade types (pg 18 "Specialized Tools"): every distinct upgrade type
     * listed on any master, totem, or crew card belonging to either keyword.
     * Concretely — character-domain Upgrade records attached (via the
     * `upgradeables` pivot) to those masters/totems, PLUS the type carried by
     * crew-card Upgrades sharing the keywords.
     *
     * @return array{tokens: list<array{id:int,name:string}>, markers: list<array{id:int,name:string}>, upgrades: list<array{id:string,name:string}>}
     */
    private function crewCardChoiceOptions(CampaignCrew $crew): array
    {
        $keywordIds = array_filter([$crew->keyword_1_id, $crew->keyword_2_id]);
        if (empty($keywordIds)) {
            return ['tokens' => [], 'markers' => [], 'upgrades' => []];
        }

        // Crew-card upgrades (crew-domain) associated with the keywords —
        // used for tokens, markers, and crew-card upgrade types.
        $crewCards = Upgrade::query()
            ->forCrews()
            ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
            ->with(['tokens:id,name', 'markers:id,name'])
            ->get(['id', 'name', 'type']);

        // Masters belonging to either keyword.
        $masters = Character::query()
            ->where('station', CharacterStationEnum::Master->value)
            ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
            ->get(['id', 'has_totem_id']);

        $masterIds = $masters->pluck('id');
        $totemIds = $masters->pluck('has_totem_id')->filter();
        $characterIds = $masterIds->merge($totemIds)->unique()->values();

        // Character-domain Upgrade records attached to those masters/totems.
        $characterUpgradeTypes = collect();
        if ($characterIds->isNotEmpty()) {
            $characterUpgradeTypes = Upgrade::query()
                ->forCharacters()
                ->whereNotNull('type')
                ->whereHas('characters', fn ($q) => $q->whereIn('characters.id', $characterIds))
                ->pluck('type'); // already cast to UpgradeTypeEnum
        }

        $shape = fn ($row) => ['id' => $row->id, 'name' => $row->name];

        // Merge types from character upgrades + crew-card upgrades, dedupe.
        $upgradeTypes = $characterUpgradeTypes
            ->merge($crewCards->pluck('type')->filter())
            ->unique(fn (UpgradeTypeEnum $t) => $t->value)
            ->map(fn (UpgradeTypeEnum $t) => ['id' => $t->value, 'name' => $t->label()])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'tokens' => $crewCards->flatMap->tokens->unique('id')->sortBy('name')->map($shape)->values()->all(),
            'markers' => $crewCards->flatMap->markers->unique('id')->sortBy('name')->map($shape)->values()->all(),
            'upgrades' => $upgradeTypes,
        ];
    }
}
