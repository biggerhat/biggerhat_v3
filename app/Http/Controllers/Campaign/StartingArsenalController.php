<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\MessageTypeEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Events\CampaignCrewUpdated;
use App\Http\Controllers\Campaign\Concerns\BroadcastsCampaignEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCorruptedPawnRequest;
use App\Http\Requests\Campaign\StoreStartingArsenalRequest;
use App\Jobs\Campaign\GenerateCombinedCrewCardImage;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Character;
use App\Models\CustomUpgrade;
use App\Support\Campaign\AftermathCatalog;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use App\Traits\Campaign\HiresTitledModelGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One-shot wizard for setting up a fresh crew's 25-ss starting arsenal
 * (rulebook pg 15). Locked once the campaign starts (unless the crew has no starting arsenal yet) — the crew is then
 * stuck with whatever rows it has (further growth happens via the weekly
 * New Hires step in Phase 7).
 */
class StartingArsenalController extends Controller
{
    use AuthorizesCampaignAccess;
    use BroadcastsCampaignEvents;
    use HiresTitledModelGroup;

    private const STARTING_BUDGET_SS = 25;

    private const MAX_LEFTOVER_SCRIP = 3;

    public const CORRUPTED_PAWNS_SOURCE = 'starting_corrupted_pawns';

    /** Corrupted Pawns (pg 146): the item goes on a model costing this much or less. */
    private const CORRUPTED_PAWNS_MAX_COST = 6;

    public function edit(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        $arsenal = CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->whereNull('annihilated_at')
            ->with(['character:id,display_name,cost,faction,station', 'customCharacter:id,display_name,cost'])
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
            'crew_card_choice_options' => fn () => AftermathCatalog::crewCardChoiceOptions($crew),
            'corrupted_pawns' => fn () => $this->corruptedPawnsProp($campaign, $crew, $arsenal),
            'starting_budget_ss' => self::STARTING_BUDGET_SS + $this->lateJoinBonus($campaign, $crew),
            'late_join_bonus' => $this->lateJoinBonus($campaign, $crew),
            'max_leftover_scrip' => self::MAX_LEFTOVER_SCRIP,
            'locked' => ! $crew->startingArsenalOpen($campaign),
        ]);
    }

    /** Scrip a late joiner gets on top of the 25 ss (pg 37): 5 per week beyond the first. Starting Anew crews use their own scrip pool. */
    private function lateJoinBonus(Campaign $campaign, CampaignCrew $crew): int
    {
        if ($crew->starting_anew_at !== null || $campaign->status !== CampaignStatusEnum::Active) {
            return 0;
        }

        return 5 * max(0, ($campaign->current_week ?? 1) - 1);
    }

    public function update(StoreStartingArsenalRequest $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        if (! $crew->startingArsenalOpen($campaign)) {
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

        // Late joiner (pg 37, same procedure as Starting Anew): the usual 25 ss starting arsenal plus 5 scrip per week
        // the campaign has run beyond the first. Standard leftover keeps the 3 scrip cap; unspent bonus carries over.
        $lateJoinBonus = $this->lateJoinBonus($campaign, $crew);

        if (! $isStartingAnew && $totalCost > self::STARTING_BUDGET_SS + $lateJoinBonus) {
            return redirect()->back()->withMessage(
                'Starting arsenal cost '.$totalCost.' ss exceeds the '.(self::STARTING_BUDGET_SS + $lateJoinBonus).' ss budget.',
                null,
                MessageTypeEnum::error,
            );
        }

        // Starting Anew: leftover is whatever scrip remains after the hire cost
        // (no max cap since the crew's bonus may legitimately exceed 3). Standard
        // start: leftover is capped at 3 per pg 15.
        if ($isStartingAnew) {
            $leftoverScrip = max(0, $crew->scrip - $totalCost);
        } else {
            $overStandard = max(0, $totalCost - self::STARTING_BUDGET_SS);
            $leftoverScrip = min(self::MAX_LEFTOVER_SCRIP, max(0, self::STARTING_BUDGET_SS - $totalCost))
                + max(0, $lateJoinBonus - $overStandard);
        }

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
            $options = AftermathCatalog::crewCardChoiceOptions($crew)[$requiredType.'s'];
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
            // The Corrupted Pawns item is bound to one of the models about to be rebuilt — pick it again afterwards.
            CampaignEquipment::query()->where('campaign_crew_id', $crew->id)->where('source', self::CORRUPTED_PAWNS_SOURCE)->delete();
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
                $this->saveCrewCardToCardCreator($userId, $crew->id, (int) $data['crew_card_effect_id'], $crewCardName, $crew->faction?->value);
            }
        });

        GenerateCombinedCrewCardImage::dispatch($crew->id)->afterCommit();

        $this->broadcastToCampaign($campaign, new CampaignCrewUpdated($crew));

        return redirect()->route('campaigns.crews.starting-arsenal.edit', [$campaign, $crew])
            ->withMessage("Starting arsenal saved ({$totalCost} ss spent, {$leftoverScrip} scrip leftover).");
    }

    /**
     * Corrupted Pawns (optional rule, pg 146): choose the starting Those Who Thirst item and the
     * arsenal model (cost 6 or less) it is permanently attached to. Re-picking replaces it.
     */
    public function storeCorruptedPawn(StoreCorruptedPawnRequest $request, Campaign $campaign, CampaignCrew $crew)
    {
        if (! ($campaign->optional_rules['corrupted_pawns'] ?? false)) {
            abort(404);
        }

        if (! $crew->startingArsenalOpen($campaign)) {
            return redirect()->back()->withMessage('The starting item is locked once the campaign is active.', null, MessageTypeEnum::error);
        }

        $data = $request->validated();

        $model = CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->active()
            ->with(['character:id,cost', 'customCharacter:id,cost'])
            ->find($data['arsenal_model_id']);
        $cost = $model ? (int) (data_get($model, 'character.cost') ?? data_get($model, 'customCharacter.cost') ?? 0) : null;

        if (! $model || $cost === null || $cost > self::CORRUPTED_PAWNS_MAX_COST) {
            return redirect()->back()->withMessage(
                'Pick a model in your arsenal that costs '.self::CORRUPTED_PAWNS_MAX_COST.' or less.',
                null,
                MessageTypeEnum::error,
            );
        }

        DB::transaction(function () use ($crew, $data, $model) {
            CampaignEquipment::query()->where('campaign_crew_id', $crew->id)->where('source', self::CORRUPTED_PAWNS_SOURCE)->delete();
            CampaignEquipment::create([
                'campaign_crew_id' => $crew->id,
                'equipment_upgrade_id' => $data['equipment_upgrade_id'],
                'source' => self::CORRUPTED_PAWNS_SOURCE,
                'bound_arsenal_model_id' => $model->id,
            ]);
        });

        $this->broadcastToCampaign($campaign, new CampaignCrewUpdated($crew));

        return redirect()->back()->withMessage('Starting Those Who Thirst item attached.');
    }

    public function destroyCorruptedPawn(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureCrewOwner($request, $campaign, $crew);

        if (! $crew->startingArsenalOpen($campaign)) {
            return redirect()->back()->withMessage('The starting item is locked once the campaign is active.', null, MessageTypeEnum::error);
        }

        CampaignEquipment::query()->where('campaign_crew_id', $crew->id)->where('source', self::CORRUPTED_PAWNS_SOURCE)->delete();

        return redirect()->back()->withMessage('Starting Those Who Thirst item removed.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CampaignArsenalModel>  $arsenal
     * @return array{items: array<int, array<string, mixed>>, models: array<int, array{id: int, name: string, cost: int}>, current: array{equipment_upgrade_id: int|null, arsenal_model_id: int|null}|null}|null
     */
    private function corruptedPawnsProp(Campaign $campaign, CampaignCrew $crew, $arsenal): ?array
    {
        if (! ($campaign->optional_rules['corrupted_pawns'] ?? false)) {
            return null;
        }

        $current = CampaignEquipment::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('source', self::CORRUPTED_PAWNS_SOURCE)
            ->first();

        return [
            'items' => array_values(array_filter(
                AftermathCatalog::thoseWhoThirst($crew->id)['items'],
                fn (array $i) => ! $i['is_omens_mark'],
            )),
            'models' => $arsenal
                ->map(fn (CampaignArsenalModel $m) => [
                    'id' => $m->id,
                    'name' => $m->label ?: (data_get($m, 'character.display_name') ?? data_get($m, 'customCharacter.display_name') ?? 'Model'),
                    'cost' => (int) (data_get($m, 'character.cost') ?? data_get($m, 'customCharacter.cost') ?? 0),
                ])
                ->filter(fn (array $m) => $m['cost'] <= self::CORRUPTED_PAWNS_MAX_COST)
                ->values()
                ->all(),
            'current' => $current ? [
                'equipment_upgrade_id' => $current->equipment_upgrade_id,
                'arsenal_model_id' => $current->bound_arsenal_model_id,
            ] : null,
        ];
    }

    /**
     * Save the selected crew card to the owner's Card Creator as a crew-domain
     * CustomUpgrade (the master card already lives there as a CustomCharacter).
     * Matched — and re-saved in place — by campaign_crew_id rather than name,
     * so renaming the crew card on a later re-save updates the same row
     * instead of orphaning it and creating a duplicate.
     */
    private function saveCrewCardToCardCreator(int $userId, int $crewId, int $crewCardId, string $name, ?string $faction): void
    {
        $crewCard = CampaignCrewCard::query()
            ->with([
                // Full field set — CombinedCrewCardEffects::build() reads
                // these same fields back once this becomes an editable copy
                // (shapeActionBlock()); a partial select here would silently
                // drop stone_cost/damage/triggers/etc. from the crew's own
                // card the first time they save an edit.
                'actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'abilities',
            ])
            ->find($crewCardId);
        if (! $crewCard) {
            return;
        }

        $existing = CustomUpgrade::query()
            ->where('campaign_crew_id', $crewId)
            ->where('is_campaign_crew_card', true)
            ->first();

        // Once synced, re-saving the Starting Arsenal wizard with the SAME
        // crew_card_effect_id (e.g. renaming, or an unrelated field change)
        // must not touch content_blocks again — the player may have since
        // added to it via the Card Creator editor, and rebuilding from the
        // immutable catalog row would silently wipe that out. Only a genuine
        // re-pick of a different crew card effect refreshes it.
        if ($existing && $existing->synced_crew_card_id === $crewCardId) {
            $existing->update(['name' => $name, 'display_name' => $name, 'faction' => $faction]);

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
                'stone_cost' => $ac->stone_cost,
                'stat' => $ac->stat,
                'stat_suits' => $ac->stat_suits,
                'stat_modifier' => $ac->stat_modifier,
                'range' => $ac->range,
                'range_type' => $ac->range_type,
                'resisted_by' => $ac->resisted_by,
                'target_number' => $ac->target_number,
                'target_suits' => $ac->target_suits,
                'damage' => $ac->damage,
                'description' => $ac->description,
                'triggers' => $ac->triggers->map(fn ($t) => [
                    'name' => $t->name,
                    'suits' => $t->suits,
                    'stone_cost' => $t->stone_cost,
                    'description' => $t->description,
                ])->all(),
            ]];
        }

        if ($existing) {
            $existing->update([
                'name' => $name,
                'display_name' => $name,
                'faction' => $faction,
                'content_blocks' => $blocks,
                'synced_crew_card_id' => $crewCardId,
            ]);

            return;
        }

        CustomUpgrade::create([
            'user_id' => $userId,
            'campaign_crew_id' => $crewId,
            'name' => $name,
            'display_name' => $name,
            'slug' => $this->uniqueUpgradeSlug($userId, $name),
            'domain' => UpgradeDomainTypeEnum::Crew->value,
            'faction' => $faction,
            'content_blocks' => $blocks,
            'is_campaign_crew_card' => true,
            'synced_crew_card_id' => $crewCardId,
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
                'miniatures' => fn ($q) => $q->select(['id', 'character_id', 'display_name', 'front_image', 'back_image']),
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
}
