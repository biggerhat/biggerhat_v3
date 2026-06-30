<?php

namespace App\Support\Campaign;

use App\Enums\GameModeTypeEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\BackAlleyDoctorResult;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\CustomCharacter;
use App\Models\Trigger;
use App\Models\Upgrade;
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
        return DB::table('campaign_arsenal_model_injuries as ami')
            ->join('campaign_arsenal_models as cam', 'cam.id', '=', 'ami.campaign_arsenal_model_id')
            ->join('upgrades as u', 'u.id', '=', 'ami.injury_upgrade_id')
            ->join('characters as c', 'c.id', '=', 'cam.character_id')
            ->where('cam.campaign_crew_id', $crewId)
            ->whereNull('cam.annihilated_at')
            ->select(
                'ami.id as pivot_id',
                'cam.id as arsenal_model_id',
                'cam.label',
                'c.display_name',
                'u.name as injury_name',
            )
            ->get()
            ->all();
    }

    /**
     * Phase 4 — all advancement-table catalogs keyed by source_table.
     *
     * @return array<string, mixed>
     */
    public static function advancementCatalogs(): array
    {
        return [
            'attack_mod' => self::triggerAdvancements('attack'),
            'tactical_mod' => self::triggerAdvancements('tactical'),
            'action' => Action::query()
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->where('campaign_advancement_kind', 'action')
                ->with('triggers:id,name,suits,stone_cost,description')
                ->orderBy('campaign_flip_value')
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
                    'triggers' => $a->triggers->map(fn (Trigger $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'suits' => $t->suits,
                        'stone_cost' => $t->stone_cost ?? 0,
                        'description' => $t->description,
                    ]),
                    'flip_value' => $a->campaign_flip_value,
                    'is_always_available' => (bool) $a->campaign_is_always_available,
                ])
                ->all(),
            'ability' => Ability::query()
                ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
                ->orderBy('campaign_flip_value')
                ->orderBy('name')
                ->get()
                ->map(fn (Ability $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'body' => $a->description,
                    'description' => $a->description,
                    'suits' => $a->suits,
                    'defensive_ability_type' => $a->defensive_ability_type,
                    'costs_stone' => (bool) $a->costs_stone,
                    'flip_value' => $a->campaign_flip_value,
                    'is_always_available' => (bool) $a->campaign_is_always_available,
                ])
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
                    'triggers' => $a->triggers->map(fn (Trigger $t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'suits' => $t->suits,
                        'stone_cost' => $t->stone_cost ?? 0,
                        'description' => $t->description,
                    ]),
                ])
                ->all(),
            'crew_card' => CampaignCrewCard::query()
                ->with(['actions:id,name', 'abilities:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (CampaignCrewCard $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'body' => $c->description,
                    'actions' => $c->actions->map(fn (Action $a) => ['id' => $a->id, 'name' => $a->name]),
                    'abilities' => $c->abilities->map(fn (Ability $a) => ['id' => $a->id, 'name' => $a->name]),
                ])
                ->all(),
        ];
    }

    /**
     * Attack/tactical-mod catalog (pg 38-41) — identical shape, keyed by
     * campaign_advancement_kind ('attack' | 'tactical').
     *
     * @return array<int, array<string, mixed>>
     */
    public static function triggerAdvancements(string $kind): array
    {
        return Trigger::query()
            ->where('game_mode_type', GameModeTypeEnum::Campaign->value)
            ->where('campaign_advancement_kind', $kind)
            ->orderBy('campaign_flip_value')
            ->orderBy('name')
            ->get()
            ->map(fn (Trigger $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'body' => $t->description,
                'description' => $t->description,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'flip_value' => $t->campaign_flip_value,
                'is_always_available' => (bool) $t->campaign_is_always_available,
                'modifier_type' => $t->campaign_modifier_type,
            ])
            ->all();
    }
}
