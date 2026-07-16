<?php

namespace App\Support\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Enums\Campaign\BackAlleyDoctorOutcomeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\CrewUpgradeRestrictionDescriptorTypeEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\UpgradeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\AdvancementAbility;
use App\Models\Campaign\AdvancementAction;
use App\Models\Campaign\AdvancementAttackMod;
use App\Models\Campaign\AdvancementTacticalMod;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignEquipment;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\Character;
use App\Models\CustomCharacter;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side catalog queries for the Aftermath wizard's phase-gated props
 * (barter equipment, treatable injuries, advancement options). Pure reads,
 * shaped to match the Vue payloads — kept out of the controller so the
 * mutation flow stays readable. All methods return plain arrays for Inertia.
 */
class AftermathCatalog
{
    /**
     * Phase 3 — barterable equipment (pg 22-30). Red-joker-only catalog entries
     * are excluded; the frontend filters the rest by BR + suit pool.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function equipment(): array
    {
        return Upgrade::query()
            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
            ->where('campaign_upgrade_kind', 'equipment')
            ->where('campaign_is_red_joker_entry', false)
            ->orderBy('campaign_br')
            ->orderBy('name')
            ->get()
            ->map(fn (Upgrade $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'br' => $u->campaign_br,
                'cc' => $u->campaign_cc,
                'is_always_available' => (bool) $u->campaign_is_always_available,
                'ttw_only' => (bool) $u->campaign_ttw_only,
                'pool_suit_a' => $u->campaign_pool_suit_a,
                'pool_suit_b' => $u->campaign_pool_suit_b,
                'body' => $u->description,
            ])
            ->all();
    }

    /**
     * The crew's active, owned Equipment — full rules text plus the actions
     * and abilities it grants (from the shared Action/Ability catalogs via
     * its Upgrade record), so the Arsenal Sheet's equipment card view has the
     * same full-text rendering as a Crew Card, and an Attack/Tactical Mod
     * advancement (pg 38-43) can target one of its actions. `category`/`type`
     * are kept in sync — the Skl Boost target picker only reads `category`.
     * `locked`/`applied_effects` surface any advancement already attached —
     * equipment has no per-instance actions[] to mutate, so those records
     * are the sole source of truth, overlaid here for display (pg 31: once
     * targeted, the leader must keep that equipment going forward).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ownedEquipment(CampaignCrew $crew, ?CustomCharacter $leader): array
    {
        $advancementInfo = self::equipmentAdvancementInfo($leader?->id);

        return CampaignEquipment::query()
            ->where('campaign_crew_id', $crew->id)
            ->active()
            ->with([
                'catalog:id,name,campaign_cc,campaign_br,description',
                'catalog.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'catalog.abilities',
            ])
            ->orderBy('id')
            ->get()
            ->map(function (CampaignEquipment $e) use ($advancementInfo) {
                $info = $advancementInfo[$e->id] ?? null;
                $category = fn (Action $a) => $a->type instanceof \BackedEnum ? $a->type->value : $a->type;

                return [
                    'id' => $e->id,
                    'source' => $e->source,
                    'name' => $e->catalog->name,
                    'cc' => $e->catalog->campaign_cc,
                    'br' => $e->catalog->campaign_br,
                    'description' => $e->catalog->description,
                    'actions' => $e->catalog->actions->map(fn (Action $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'category' => $category($a),
                        'type' => $category($a),
                        'is_signature' => (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from morphedByMany)
                        'stone_cost' => $a->stone_cost,
                        'range' => $a->range,
                        'range_type' => $a->range_type instanceof \BackedEnum ? $a->range_type->value : $a->range_type,
                        'stat' => $a->stat,
                        'stat_suits' => $a->stat_suits,
                        'stat_modifier' => $a->stat_modifier instanceof \BackedEnum ? $a->stat_modifier->value : $a->stat_modifier,
                        'resisted_by' => $a->resisted_by,
                        'target_number' => $a->target_number,
                        'target_suits' => $a->target_suits,
                        'damage' => $a->damage,
                        'description' => $a->description,
                        // Base catalog triggers plus any Trigger-type Attack/
                        // Tactical Mod advancement applied to this specific
                        // action (pg 38-43) — rendered as a real trigger, not
                        // just the "+ Effect" applied_effects label below.
                        'triggers' => array_merge(self::triggerSummaries($a->triggers), $info['triggers_by_action_id'][$a->id] ?? []),
                    ])->all(),
                    'abilities' => $e->catalog->abilities->map(fn (Ability $a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'suits' => $a->suits,
                        'defensive_ability_type' => $a->defensive_ability_type,
                        'costs_stone' => (bool) $a->costs_stone,
                        'description' => $a->description,
                    ])->all(),
                    'locked' => $info !== null,
                    'applied_effects' => $info['applied_effects'] ?? [],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{applied_effects: array<int, string>, triggers_by_action_id: array<int, array<int, array<string, mixed>>>}>
     */
    private static function equipmentAdvancementInfo(?int $leaderId): array
    {
        if ($leaderId === null) {
            return [];
        }

        $rows = CampaignLeaderAdvancement::query()
            ->where('custom_character_id', $leaderId)
            ->whereNotNull('from_equipment_id')
            ->whereIn('source_table', [AdvancementTableEnum::AttackMod, AdvancementTableEnum::TacticalMod])
            ->with('appliedToAction:id,name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $catalogRow = $row->source_table === AdvancementTableEnum::AttackMod
                ? AdvancementAttackMod::query()->with('trigger')->find($row->advancement_catalog_id)
                : AdvancementTacticalMod::query()->with('trigger')->find($row->advancement_catalog_id);
            $effectName = 'Advancement';
            if ($catalogRow) {
                $effectName = $catalogRow->trigger ? $catalogRow->trigger->name : $catalogRow->name;
            }
            $actionName = $row->appliedToAction?->name;

            $result[$row->from_equipment_id]['applied_effects'][] = $actionName ? "{$effectName} — {$actionName}" : $effectName;

            // A Trigger-type modifier grants a real, renderable Trigger —
            // merged into the target action's own triggers[] in
            // ownedEquipment() so the card shows full rules text instead of
            // just the "+ Effect — Action" applied_effects label above.
            if ($catalogRow?->trigger && $row->applied_to_action_id !== null) {
                $result[$row->from_equipment_id]['triggers_by_action_id'][$row->applied_to_action_id][] = [
                    'id' => $catalogRow->trigger->id,
                    'name' => $catalogRow->trigger->name,
                    'suits' => $catalogRow->trigger->suits,
                    'stone_cost' => $catalogRow->trigger->stone_cost ?? 0,
                    'description' => $catalogRow->trigger->description,
                ];
            }
        }

        return $result;
    }

    /**
     * Phase 5 — the Back-Alley Doctor result table. The player makes the doctor
     * flip at the table (pg 33) and picks the result they got by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function doctorResults(): array
    {
        return BackAlleyDoctorResult::query()
            ->orderByRaw('is_black_joker desc')
            ->orderBy('flip_value_min')
            ->orderByRaw('is_red_joker desc')
            ->get()
            ->map(fn (BackAlleyDoctorResult $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'body' => $r->body,
                'outcome_kind' => $r->outcome_kind->value,
            ])
            ->all();
    }

    /**
     * Phase 6 — the full injury catalog. The player resolves the injury flip at
     * the table (pg 34-36) and picks the matching injury by name from this list.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function injuries(): array
    {
        return Upgrade::query()
            ->where('campaign_upgrade_kind', 'injury')
            ->orderBy('campaign_suit_pool')
            ->orderBy('campaign_flip_value')
            ->orderBy('name')
            ->get()
            ->map(fn (Upgrade $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'suit_pool' => $u->campaign_suit_pool,
                'flip_value' => $u->campaign_flip_value,
            ])
            ->all();
    }

    /**
     * Phase 5 — the crew's treatable injuries (one row per attached injury).
     *
     * @return array<int, object>
     */
    public static function crewInjuries(int $crewId): array
    {
        $modelInjuries = DB::table('campaign_arsenal_model_injuries as ami')
            ->join('campaign_arsenal_models as cam', 'cam.id', '=', 'ami.campaign_arsenal_model_id')
            ->join('upgrades as u', 'u.id', '=', 'ami.injury_upgrade_id')
            ->join('characters as c', 'c.id', '=', 'cam.character_id')
            ->where('cam.campaign_crew_id', $crewId)
            ->whereNull('cam.annihilated_at')
            ->select(
                'ami.id as pivot_id',
                'cam.id as arsenal_model_id',
                DB::raw('NULL as custom_character_id'),
                'cam.label',
                'c.display_name',
                'u.name as injury_name',
            );

        // Leader/totem injuries (pg 34-36) — stored via custom_character_id
        // instead of campaign_arsenal_model_id (leaders/totems are
        // CustomCharacter rows, not CampaignArsenalModel rows).
        $customCharInjuries = DB::table('campaign_arsenal_model_injuries as ami')
            ->join('custom_characters as cc', 'cc.id', '=', 'ami.custom_character_id')
            ->join('upgrades as u', 'u.id', '=', 'ami.injury_upgrade_id')
            ->where('cc.campaign_crew_id', $crewId)
            ->whereNull('cc.annihilated_at')
            ->select(
                'ami.id as pivot_id',
                DB::raw('NULL as arsenal_model_id'),
                'cc.id as custom_character_id',
                DB::raw('NULL as label'),
                'cc.name as display_name',
                'u.name as injury_name',
            );

        return $modelInjuries->unionAll($customCharInjuries)->get()->all();
    }

    /**
     * Phase 4 — all advancement-table catalogs keyed by source_table.
     *
     * @return array<string, mixed>
     */
    public static function advancementCatalogs(?CampaignCrew $crew = null): array
    {
        // Tier-4 Crew Card: exclude effects the crew already holds (its
        // starter effect + any effects already borrowed) so the same effect
        // can't be picked twice. Split by source type since CampaignCrewCard
        // and Upgrade ids can collide across the two tables.
        //
        // The Upgrade (crew_upgrade) source picks a single action/ability/
        // trigger (pg 32), not the whole card, so "already held" there is
        // tracked per item — keyed "{upgradeId}:{itemType}:{itemId}" since
        // the same effect appearing on two different cards is a distinct
        // pick (its restriction/limitation travels with the card it came
        // from). Rows predating that granularity change (crew_card_item_type
        // null) have no way to know which single item was actually picked,
        // so they're treated as "this crew already holds everything this
        // card ever granted" and the whole card is skipped.
        $heldCampaignCrewCardIds = [];
        $heldUpgradeItemKeys = [];
        $legacyWholeHeldUpgradeIds = [];
        if ($crew) {
            $heldCampaignCrewCardIds = array_values(array_filter([$crew->crew_card_effect_id]));
            foreach ($crew->crewCardAdvancements as $advancement) {
                if ($advancement->crew_card_effect_type === Upgrade::class) {
                    if ($advancement->crew_card_item_type && $advancement->crew_card_item_id) {
                        $heldUpgradeItemKeys[] = "{$advancement->crew_card_effect_id}:{$advancement->crew_card_item_type}:{$advancement->crew_card_item_id}";
                    } else {
                        $legacyWholeHeldUpgradeIds[] = $advancement->crew_card_effect_id;
                    }
                } else {
                    $heldCampaignCrewCardIds[] = $advancement->crew_card_effect_id;
                }
            }
        }

        // Tier-4 Crew Card (pg 32, 54): masters' own keywords, used to filter
        // the keyword-matched Crew Card Upgrade pool below.
        $keywordIds = $crew ? array_values(array_filter([$crew->keyword_1_id, $crew->keyword_2_id])) : [];

        return [
            'attack_mod' => self::attackTacticalAdvancements(AdvancementAttackMod::class),
            'tactical_mod' => self::attackTacticalAdvancements(AdvancementTacticalMod::class),
            'action' => AdvancementAction::query()
                ->with('action.triggers:id,name,suits,stone_cost,description')
                ->orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('talent_name')
                ->get()
                ->map(function (AdvancementAction $row) {
                    $source = $row->action;
                    $stat = $row->stat_block ?? [];

                    return [
                        'id' => $row->id,
                        'name' => $source->name ?? $row->talent_name,
                        'body' => $source->description ?? $row->effect_text,
                        'description' => $source->description ?? $row->effect_text,
                        'type' => $source->type ?? ($stat['type'] ?? null),
                        'stat' => $source->stat ?? ($stat['stat'] ?? null),
                        'stat_suits' => $source->stat_suits ?? ($stat['stat_suits'] ?? null),
                        'stat_modifier' => $source->stat_modifier ?? ($stat['stat_modifier'] ?? null),
                        'range' => $source->range ?? ($stat['range'] ?? null),
                        'range_type' => $source->range_type ?? ($stat['range_type'] ?? null),
                        'resisted_by' => $source->resisted_by ?? ($stat['resisted_by'] ?? null),
                        'target_number' => $source->target_number ?? ($stat['target_number'] ?? null),
                        'target_suits' => $source->target_suits ?? ($stat['target_suits'] ?? null),
                        'damage' => $source->damage ?? ($stat['damage'] ?? null),
                        'stone_cost' => $source->stone_cost ?? 0,
                        // The catalog row's own admin-set flag, whether this
                        // row is a lookup or a bespoke entry.
                        'is_signature' => (bool) $row->is_signature,
                        'triggers' => self::triggerSummaries($source?->triggers ?? collect()), // @phpstan-ignore nullsafe.neverNull (action() is a nullable BelongsTo — bespoke rows have no linked Action)
                        'flip_value' => $row->flip_value,
                        'is_always_available' => (bool) $row->is_always_available,
                        'is_joker' => (bool) $row->is_joker,
                    ];
                })
                ->all(),
            'ability' => AdvancementAbility::query()
                ->with('ability')
                ->orderByRaw('flip_value IS NULL, flip_value ASC')
                ->orderBy('talent_name')
                ->get()
                ->map(function (AdvancementAbility $row) {
                    $source = $row->ability;

                    return [
                        'id' => $row->id,
                        'name' => $source->name ?? $row->talent_name,
                        'body' => $source->description ?? $row->effect_text,
                        'description' => $source->description ?? $row->effect_text,
                        'suits' => $source->suits ?? $row->suits,
                        'defensive_ability_type' => $source->defensive_ability_type ?? $row->defensive_ability_type,
                        'costs_stone' => (bool) ($source->costs_stone ?? false),
                        'flip_value' => $row->flip_value,
                        'is_always_available' => (bool) $row->is_always_available,
                        'is_joker' => (bool) $row->is_joker,
                    ];
                })
                ->all(),
            'totem' => CustomCharacter::query()
                ->where('is_campaign_totem_template', true)
                ->orderBy('campaign_totem_flip_value')
                ->orderBy('name')
                ->get()
                ->map(fn (CustomCharacter $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'flip_value' => $c->campaign_totem_flip_value,
                    'is_black_joker' => (bool) $c->campaign_is_black_joker_totem,
                    'is_red_joker' => (bool) $c->campaign_is_red_joker_totem,
                ])
                ->all(),
            'summoning' => Action::query()
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('campaign_advancement_kind', 'summoning')
                ->with('triggers:id,name,suits,stone_cost,description')
                ->orderBy('name')
                ->get()
                ->map(fn (Action $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'body' => $a->description,
                    'description' => $a->description,
                    'type' => $a->type,
                    'stat' => $a->stat,
                    'stat_suits' => $a->stat_suits,
                    'stat_modifier' => $a->stat_modifier,
                    'range' => $a->range,
                    'range_type' => $a->range_type,
                    'resisted_by' => $a->resisted_by,
                    'target_number' => $a->target_number,
                    'target_suits' => $a->target_suits,
                    'damage' => $a->damage,
                    'stone_cost' => $a->stone_cost ?? 0,
                    'is_signature' => (bool) $a->is_signature,
                    'triggers' => self::triggerSummaries($a->triggers),
                ])
                ->all(),
            // Tier-4 Crew Card (pg 32, 54) pool: rulebook allows either (a) the
            // fixed pg 15-16 generic list — the same catalog Starting Arsenal
            // draws from, source 'campaign_crew_card', where each admin-authored
            // row already models a single named effect so the whole row can
            // stay the pickable unit — or (b) any single action, ability, or
            // standalone trigger printed on a real Crew Card Upgrade sharing one
            // of the crew's keywords, source 'crew_upgrade'. Pg 32: "'effect'
            // refers to a single ability, action, or trigger. Other effects may
            // not be chosen" — so (b) is a flat pool of individual items, not
            // whole cards, unlike (a). No master-selection step either way:
            // keyword membership on the Upgrade itself is the sole eligibility
            // gate for (b).
            'crew_card' => [
                ...CampaignCrewCard::query()
                    ->when(! empty($heldCampaignCrewCardIds), fn ($q) => $q->whereNotIn('id', $heldCampaignCrewCardIds))
                    ->with(['actions:id,name', 'abilities:id,name'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (CampaignCrewCard $c) => [
                        'id' => $c->id,
                        'source' => 'campaign_crew_card',
                        'name' => $c->name,
                        'body' => $c->description,
                        'front_image' => $c->front_image,
                        'actions' => $c->actions->map(fn (Action $a) => ['id' => $a->id, 'name' => $a->name]),
                        'abilities' => $c->abilities->map(fn (Ability $a) => ['id' => $a->id, 'name' => $a->name]),
                        // Whether taking this borrowed effect also requires picking
                        // a token/marker/upgrade type from the constrained pool
                        // (pg 17-18) — same mechanism as the Starting Arsenal pick.
                        'requires_token_choice' => (bool) $c->requires_token_choice,
                        'requires_marker_choice' => (bool) $c->requires_marker_choice,
                        'requires_upgrade_type_choice' => (bool) $c->requires_upgrade_type_choice,
                    ])
                    ->all(),
                ...self::crewUpgradeCrewCardItems($keywordIds, $heldUpgradeItemKeys, $legacyWholeHeldUpgradeIds),
            ],
        ];
    }

    /**
     * Flat pool of individual action/ability/(standalone) trigger items
     * borrowable from a keyword-matched Crew Card Upgrade (pg 32, 54) — one
     * entry per pickable item, not one per card, since "'effect' refers to a
     * single ability, action, or trigger." Shaped to match the plain
     * 'action'/'ability' advancement-catalog rows above (same field names)
     * so the same ActionCard/AbilityCard/TriggerCard components can render
     * them directly; a nested action's own triggers are included on it
     * (pg 32: "if an action is chosen, it will automatically come with any
     * associated triggers") but aren't separately pickable rows.
     *
     * @param  array<int, int>  $keywordIds
     * @param  array<int, string>  $heldItemKeys  "{upgradeId}:{itemType}:{itemId}" already picked
     * @param  array<int, int>  $legacyWholeHeldUpgradeIds  Upgrade ids granted whole under the pre-granularity scheme
     * @return array<int, array<string, mixed>>
     */
    private static function crewUpgradeCrewCardItems(array $keywordIds, array $heldItemKeys, array $legacyWholeHeldUpgradeIds): array
    {
        if (empty($keywordIds)) {
            return [];
        }

        $rows = [];

        Upgrade::query()
            ->forCrews()
            ->whereHas('keywords', fn ($k) => $k->whereIn('keywords.id', $keywordIds))
            ->when(! empty($legacyWholeHeldUpgradeIds), fn ($q) => $q->whereNotIn('id', $legacyWholeHeldUpgradeIds))
            ->with(['actions:id,name,type,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description,is_signature', 'actions.triggers:id,name,suits,stone_cost,description', 'abilities:id,name,suits,defensive_ability_type,costs_stone,description', 'triggers:id,name,suits,stone_cost,description'])
            ->orderBy('name')
            ->get()
            ->each(function (Upgrade $u) use (&$rows, $heldItemKeys) {
                $held = fn (string $type, int $id) => in_array("{$u->id}:{$type}:{$id}", $heldItemKeys, true);
                $qualifier = fn (?string $restriction, CrewUpgradeRestrictionDescriptorTypeEnum $type) => $restriction === null
                    ? null
                    : CrewUpgradeRestrictionEnum::tryFrom($restriction)?->descriptor($type);

                foreach ($u->actions as $a) {
                    if ($a->pivot->borrow_exclusion !== null || $held('action', $a->id)) { // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        continue;
                    }
                    $rows[] = [
                        'id' => $a->id,
                        'item_type' => 'action',
                        'source' => 'crew_upgrade',
                        'source_id' => $u->id,
                        'source_name' => $u->name,
                        'name' => $a->name,
                        'qualifier' => $qualifier($a->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Action), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        'body' => $a->description,
                        'description' => $a->description,
                        'type' => $a->type,
                        'stat' => $a->stat,
                        'stat_suits' => $a->stat_suits,
                        'stat_modifier' => $a->stat_modifier,
                        'range' => $a->range,
                        'range_type' => $a->range_type,
                        'resisted_by' => $a->resisted_by,
                        'target_number' => $a->target_number,
                        'target_suits' => $a->target_suits,
                        'damage' => $a->damage,
                        'stone_cost' => $a->stone_cost ?? 0,
                        'is_signature' => (bool) $a->is_signature,
                        'triggers' => self::triggerSummaries($a->triggers),
                    ];
                }
                foreach ($u->abilities as $a) {
                    if ($a->pivot->borrow_exclusion !== null || $held('ability', $a->id)) { // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        continue;
                    }
                    $rows[] = [
                        'id' => $a->id,
                        'item_type' => 'ability',
                        'source' => 'crew_upgrade',
                        'source_id' => $u->id,
                        'source_name' => $u->name,
                        'name' => $a->name,
                        'qualifier' => $qualifier($a->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Ability), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        'body' => $a->description,
                        'description' => $a->description,
                        'suits' => $a->suits,
                        'defensive_ability_type' => $a->defensive_ability_type,
                        'costs_stone' => (bool) $a->costs_stone,
                    ];
                }
                // Standalone triggers only (pg 32: "a trigger may only be
                // chosen in the case that a trigger is listed individually on
                // the card") — nested action triggers already ride along with
                // their action above, not offered as their own pickable row.
                foreach ($u->triggers as $t) {
                    if ($t->pivot->borrow_exclusion !== null || $held('trigger', $t->id)) { // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        continue;
                    }
                    $rows[] = [
                        'id' => $t->id,
                        'item_type' => 'trigger',
                        'source' => 'crew_upgrade',
                        'source_id' => $u->id,
                        'source_name' => $u->name,
                        'name' => $t->name,
                        'qualifier' => $qualifier($t->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Trigger), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                        'body' => $t->description,
                        'description' => $t->description,
                        'suits' => $t->suits,
                        'stone_cost' => $t->stone_cost,
                    ];
                }
            });

        return $rows;
    }

    /**
     * Constrained pool for a crew card that requires a token/marker/upgrade
     * choice (pg 17): items listed on a crew card belonging to a master
     * sharing either of the crew's keywords. Shared between Starting Arsenal
     * (the starter effect) and the Tier-4 Crew Card advancement (borrowed
     * effects, pg 32, 54) — the pool is identical, scoped by the crew's own
     * keywords either way.
     *
     * @return array{tokens: array<int, array{id: int, name: string}>, markers: array<int, array{id: int, name: string}>, upgrades: array<int, array{id: string, name: string}>}
     */
    public static function crewCardChoiceOptions(CampaignCrew $crew): array
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

    /**
     * The crew's owned equipment (pg 19), grouped by catalog upgrade so
     * multiple owned copies report as one entry with plentiful = copies
     * owned. Shaped to drop straight into a GameCrewMember's
     * `attached_upgrades` — shared by Game Tracker's in-play "attach
     * upgrade" editor and by equipment assignment at crew selection.
     *
     * Optional `$leader` merges any Trigger-type Attack/Tactical Mod
     * advancement into its target action's own triggers, matching
     * ownedEquipment() (the Arsenal Sheet's equivalent) — without this the
     * Game Tracker showed the action but silently dropped the advancement's
     * trigger. Since this method groups by catalog type (fungible copies,
     * not the specific instance an advancement is keyed to), a trigger shows
     * on the group if ANY owned copy carries it.
     *
     * Also flags `is_advanced` (pg 31: once an Attack/Tactical Mod
     * advancement targets an equipment-granted action, the Leader must keep
     * re-equipping that specific item) — enforced as an assignment
     * restriction in GameSetupController::resolveEquipmentAssignments().
     *
     * @return array<int, array{id: int, name: string, slug: string, front_image: string|null, back_image: string|null, type: mixed, plentiful: int, power_bar_count: int|null, description: string|null, is_advanced: bool, actions: array<int, array<string, mixed>>, abilities: array<int, array<string, mixed>>}>
     */
    public static function ownedEquipmentForAttachment(CampaignCrew $crew, ?CustomCharacter $leader = null): array
    {
        $advancementInfo = self::equipmentAdvancementInfo($leader?->id);

        return CampaignEquipment::query()
            ->where('campaign_crew_id', $crew->id)
            ->active()
            ->with([
                'catalog:id,name,slug,front_image,back_image,type,power_bar_count,description',
                // Full action/ability text so the in-play "attach upgrade"
                // drawer can render ActionCard/AbilityCard instead of just
                // the bare description — same pattern as crewCardEffect.
                'catalog.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                'catalog.abilities',
            ])
            ->get()
            ->filter(fn (CampaignEquipment $e) => $e->catalog !== null)
            ->groupBy('equipment_upgrade_id')
            ->map(function (Collection $owned) use ($advancementInfo) {
                $u = $owned->first()->catalog;
                $u->actions->each(
                    fn (Action $a) => $a->is_signature = (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from morphedByMany)
                );
                // Merge in any Trigger-type mod keyed to any owned instance
                // of this catalog upgrade (see docblock above).
                $triggersByActionId = [];
                $isAdvanced = false;
                foreach ($owned->pluck('id') as $instanceId) {
                    if (isset($advancementInfo[$instanceId])) {
                        $isAdvanced = true;
                    }
                    foreach ($advancementInfo[$instanceId]['triggers_by_action_id'] ?? [] as $actionId => $triggers) {
                        $triggersByActionId[$actionId] = array_merge($triggersByActionId[$actionId] ?? [], $triggers);
                    }
                }

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'slug' => $u->slug,
                    'front_image' => $u->front_image,
                    'back_image' => $u->back_image,
                    'type' => $u->type,
                    'plentiful' => $owned->count(),
                    'power_bar_count' => $u->power_bar_count,
                    'description' => $u->description,
                    'is_advanced' => $isAdvanced,
                    'actions' => $u->actions->map(fn (Action $a) => [
                        'name' => $a->name,
                        'type' => $a->type,
                        'is_signature' => $a->is_signature,
                        'stone_cost' => $a->stone_cost,
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
                        'triggers' => array_merge(self::triggerSummaries($a->triggers), $triggersByActionId[$a->id] ?? []),
                    ])->all(),
                    'abilities' => $u->abilities->map(fn (Ability $ab) => [
                        'name' => $ab->name,
                        'suits' => $ab->suits,
                        'defensive_ability_type' => $ab->defensive_ability_type,
                        'costs_stone' => $ab->costs_stone,
                        'description' => $ab->description,
                    ])->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Named (not inline) so its array shape is declared once — two different
     * `.map()` call sites in this class build this exact sub-shape and an
     * inline closure per call site trips up Larastan's collection-template
     * covariance check across them.
     *
     * @param  Collection<int, Trigger>  $triggers
     * @return array<int, array{id: int, name: string, suits: string|null, stone_cost: int, description: string|null}>
     */
    private static function triggerSummaries(Collection $triggers): array
    {
        return $triggers->map(fn (Trigger $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'suits' => $t->suits,
            'stone_cost' => $t->stone_cost ?? 0,
            'description' => $t->description,
        ])->all();
    }

    /**
     * Attack/tactical-mod catalog (pg 38-43) — identical shape between the
     * two tables, so one query shape covers both models. `is_black_joker` /
     * `is_red_joker` both true on a row means "Any Joker" (Attack Mod's
     * Cruel Lessons / Consult the Bones — either color qualifies); exactly
     * one true means that specific card only (Tactical Mod's Illumination
     * of Illios / Darkness of Delios grant different triggers per color).
     *
     * @param  class-string<AdvancementAttackMod>|class-string<AdvancementTacticalMod>  $modelClass
     * @return array<int, array<string, mixed>>
     */
    private static function attackTacticalAdvancements(string $modelClass): array
    {
        /** @var Builder<AdvancementAttackMod|AdvancementTacticalMod> $query */
        $query = $modelClass::query();

        return $query
            ->with('trigger')
            ->orderByRaw('flip_value IS NULL, flip_value ASC')
            ->orderBy('name')
            ->get()
            ->map(function (AdvancementAttackMod|AdvancementTacticalMod $row) {
                $source = $row->trigger;

                return [
                    'id' => $row->id,
                    'name' => $source->name ?? $row->name,
                    'body' => $source->description ?? $row->effect_text,
                    'description' => $source->description ?? $row->effect_text,
                    'suits' => $source->suits ?? $row->suit,
                    'stone_cost' => $source->stone_cost ?? 0,
                    'flip_value' => $row->flip_value,
                    'is_always_available' => (bool) $row->is_always_available,
                    'is_black_joker' => (bool) $row->is_black_joker,
                    'is_red_joker' => (bool) $row->is_red_joker,
                    'modifier_type' => $row->modifier_type,
                    'skl_from' => $row->skl_from,
                    'skl_from_max' => $row->skl_from_max,
                    'skl_to' => $row->skl_to,
                ];
            })
            ->all();
    }

    /**
     * Phase 6 review-screen rundown of what Phases 1-5 already committed —
     * those phases persist immediately on submit (see `lockAndAdvance()`),
     * so this reads back already-written rows rather than trusting client
     * state. Lets the player sanity-check their picks before the Phase 6
     * submit locks the whole Aftermath.
     *
     * @return array<string, mixed>
     */
    public static function phaseSummary(CampaignAftermath $aftermath): array
    {
        $barterRows = CampaignEquipment::query()
            ->where('acquired_aftermath_id', $aftermath->id)
            ->whereIn('source', ['barter', 'joker'])
            ->with('catalog:id,name,campaign_cc')
            ->get();

        $advancementRows = CampaignLeaderAdvancement::query()
            ->where('source_aftermath_id', $aftermath->id)
            ->get();

        $doctorRows = DB::table('campaign_aftermath_doctor')
            ->where('campaign_aftermath_id', $aftermath->id)
            ->get();

        $arsenalNames = CampaignArsenalModel::query()
            ->whereIn('id', $doctorRows->pluck('target_arsenal_model_id')->filter()->all())
            ->with('character:id,display_name')
            ->get()
            ->keyBy('id');
        $customCharNames = CustomCharacter::query()
            ->whereIn('id', $doctorRows->pluck('target_custom_character_id')->filter()->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        return [
            'hand_size' => $aftermath->hand_drawn['size'] ?? null,
            'scrip_earned' => (int) $aftermath->scrip_earned,
            'barter' => $barterRows->map(fn (CampaignEquipment $e) => [
                'name' => $e->catalog->name ?? 'Unknown item',
                'cc' => $e->catalog->campaign_cc ?? 0,
            ])->all(),
            'barter_total_cc' => (int) $barterRows->sum(fn (CampaignEquipment $e) => $e->catalog->campaign_cc ?? 0),
            'advancements' => $advancementRows->map(fn (CampaignLeaderAdvancement $row) => [
                'table' => $row->source_table->label(),
                'name' => self::advancementDisplayName($row),
                'target' => self::advancementTargetName($row),
            ])->all(),
            'doctor_attempts' => $doctorRows->map(fn ($row) => [
                'target' => $row->target_arsenal_model_id
                    ? ($arsenalNames->get($row->target_arsenal_model_id)?->character->display_name ?? 'Model')
                    : ($customCharNames->get($row->target_custom_character_id)->name ?? 'Leader/Totem'),
                'outcome' => BackAlleyDoctorOutcomeEnum::from($row->outcome)->label(),
            ])->all(),
        ];
    }

    /**
     * Best-effort human name for an advancement pick — `advancement_catalog_id`
     * points at a different table depending on `source_table`, so this walks
     * the same per-table shape `LeaderAdvancementService::create()` writes.
     */
    private static function advancementDisplayName(CampaignLeaderAdvancement $row): string
    {
        $name = match ($row->source_table) {
            AdvancementTableEnum::AttackMod => AdvancementAttackMod::find($row->advancement_catalog_id)?->name,
            AdvancementTableEnum::TacticalMod => AdvancementTacticalMod::find($row->advancement_catalog_id)?->name,
            AdvancementTableEnum::Action => self::advancementActionName($row->advancement_catalog_id),
            AdvancementTableEnum::Ability => self::advancementAbilityName($row->advancement_catalog_id),
            AdvancementTableEnum::Totem => CustomCharacter::find($row->advancement_catalog_id)?->name,
            AdvancementTableEnum::Summoning => Action::find($row->catalog_core_id)?->name,
            // crew_upgrade source (pg 32, single-item picks): advancement_catalog_id
            // is the picked item's own id — resolve via whichever table
            // create() tagged it as in free_choice. Everything else
            // (campaign_crew_card source, or a legacy pre-granularity
            // crew_upgrade row with no free_choice) is a whole-card id.
            AdvancementTableEnum::CrewCard => isset($row->free_choice['crew_card_upgrade_id'])
                ? match ($row->free_choice['crew_card_item_type'] ?? null) {
                    'action' => Action::find($row->advancement_catalog_id)?->name,
                    'ability' => Ability::find($row->advancement_catalog_id)?->name,
                    'trigger' => Trigger::find($row->advancement_catalog_id)?->name,
                    default => null,
                }
            : (CampaignCrewCard::find($row->advancement_catalog_id)?->name ?? Upgrade::find($row->advancement_catalog_id)?->name), // @phpstan-ignore nullsafe.neverNull
        };

        return $name ?? $row->source_table->label();
    }

    /**
     * Who an advancement actually landed on — the Leader by default, the
     * routed Totem (pg 31, 38-43), or the piece of Equipment it modifies.
     * `custom_character_id` is always the row's owning Leader; a set
     * `applied_to_custom_character_id` overrides that with a Totem reroute.
     */
    private static function advancementTargetName(CampaignLeaderAdvancement $row): string
    {
        if ($row->from_equipment_id !== null) {
            $equipment = CampaignEquipment::query()->with('catalog:id,name')->find($row->from_equipment_id);

            return $equipment?->catalog->name ?? 'Equipment';
        }

        $targetId = $row->applied_to_custom_character_id ?? $row->custom_character_id;

        return CustomCharacter::find($targetId)?->name ?? 'Leader'; // @phpstan-ignore nullsafe.neverNull
    }

    private static function advancementActionName(?int $advancementActionId): ?string
    {
        if ($advancementActionId === null) {
            return null;
        }
        $row = AdvancementAction::query()->with('action:id,name')->find($advancementActionId);

        return $row?->action->name ?? $row?->talent_name;
    }

    private static function advancementAbilityName(?int $advancementAbilityId): ?string
    {
        if ($advancementAbilityId === null) {
            return null;
        }
        $row = AdvancementAbility::query()->with('ability:id,name')->find($advancementAbilityId);

        return $row?->ability->name ?? $row?->talent_name;
    }

    /**
     * The specific action an Attack/Tactical Mod advancement modifies —
     * `applied_to_action_index` indexes the target's `actions[]` JSON
     * (Leader or Totem), or `applied_to_action_id` is a real `actions.id`
     * row for an Equipment-granted action.
     */
    private static function advancementModActionName(CampaignLeaderAdvancement $row): ?string
    {
        if ($row->from_equipment_id !== null) {
            return $row->applied_to_action_id ? Action::find($row->applied_to_action_id)?->name : null;
        }

        $target = CustomCharacter::find($row->applied_to_custom_character_id ?? $row->custom_character_id);
        $idx = $row->applied_to_action_index;

        return ($target && $idx >= 0) ? ($target->actions[$idx]['name'] ?? null) : null;
    }

    /**
     * The Totem advancement's own created unit — unlike every other table,
     * `advancement_catalog_id` here is the stock template row, not the
     * crew-specific instance (the player names/sizes it themselves in the
     * Aftermath wizard, see `LeaderAdvancementService::createTotemFromTemplate()`).
     */
    private static function advancementTotemUnitName(CampaignLeaderAdvancement $row): ?string
    {
        $leaderCrewId = CustomCharacter::find($row->custom_character_id)?->campaign_crew_id;
        if ($leaderCrewId === null) {
            return null;
        }

        return CustomCharacter::query()
            ->where('campaign_crew_id', $leaderCrewId)
            ->where('is_campaign_totem', true)
            ->where('current', true)
            ->value('name');
    }

    /**
     * Ordered display chain for the Arsenal Sheet Advancement Log, joined
     * with " > " client-side:
     * - Attack/Tactical Mod: Mod name > Action name > Unit/Equipment name
     * - Action/Ability/Summoning: Effect name > Unit name
     * - Totem: the crew's actual current totem's own (possibly renamed) name
     * - Crew Card: the picked Ability/Action/Trigger's own name for a
     *   granular pick (pg 32), or the card's name plus a comma-joined list of
     *   everything it grants for a whole-card pick (no unit context either
     *   way — it's a card-level effect, not applied to a model)
     *
     * @return array<int, string>
     */
    public static function advancementContextChain(CampaignLeaderAdvancement $row): array
    {
        return match ($row->source_table) {
            AdvancementTableEnum::AttackMod, AdvancementTableEnum::TacticalMod => array_values(array_filter([
                self::advancementDisplayName($row),
                self::advancementModActionName($row),
                self::advancementTargetName($row),
            ])),
            AdvancementTableEnum::Action, AdvancementTableEnum::Ability, AdvancementTableEnum::Summoning => array_values(array_filter([
                self::advancementDisplayName($row),
                self::advancementTargetName($row),
            ])),
            AdvancementTableEnum::Totem => array_values(array_filter([self::advancementTotemUnitName($row)])),
            AdvancementTableEnum::CrewCard => self::advancementCrewCardContextChain($row),
        };
    }

    /**
     * A granular pick (pg 32, crew_upgrade source with free_choice) already
     * names the single action/ability/trigger — nothing more to add. A
     * whole-card pick (the generic pg 15-16 catalog, or a legacy
     * pre-granularity crew_upgrade row) only shows the card's own name via
     * advancementDisplayName(), so append what it actually grants.
     *
     * @return array<int, string>
     */
    private static function advancementCrewCardContextChain(CampaignLeaderAdvancement $row): array
    {
        $name = self::advancementDisplayName($row);

        if (isset($row->free_choice['crew_card_upgrade_id'])) {
            return array_values(array_filter([$name]));
        }

        $effect = CampaignCrewCard::find($row->advancement_catalog_id) ?? Upgrade::find($row->advancement_catalog_id);
        $granted = $effect
            ? $effect->actions->pluck('name')->merge($effect->abilities->pluck('name'))->all()
            : [];

        return array_values(array_filter([$name, $granted ? implode(', ', $granted) : null]));
    }
}
