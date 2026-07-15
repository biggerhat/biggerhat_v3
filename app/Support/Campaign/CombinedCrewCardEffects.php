<?php

namespace App\Support\Campaign;

use App\Enums\CrewUpgradeRestrictionDescriptorTypeEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Builds the flat, ordered list of effects a crew's single generated Crew
 * Card image (CampaignCrew::crew_card_front_image) actually renders: the
 * starter effect's own actions/abilities, followed by every currently-held
 * Tier-4 borrow's actions/abilities/triggers, in acquisition order.
 *
 * A Tier-4 effect borrowed from a real Crew Card Upgrade (source
 * 'crew_upgrade') carries the same `restriction` a standard, non-Campaign
 * Crew Upgrade already enforces — surfaced here as the qualifying text a
 * real crew card prints above a restricted effect (e.g. "Friendly Ten
 * Thunders models gain the following action:"), via
 * CrewUpgradeRestrictionEnum::descriptor(). The generic pg 15-16 catalog
 * (CampaignCrewCard) has no such field, so its effects — the starter and any
 * 'campaign_crew_card'-sourced Tier-4 borrow — never carry a qualifier.
 */
class CombinedCrewCardEffects
{
    /**
     * @return array<int, array{type: 'action'|'ability'|'trigger', qualifier: string|null, data: array<string, mixed>}>
     */
    public static function build(CampaignCrew $crew): array
    {
        $items = [];

        $starter = $crew->crewCardEffect;
        if ($starter) {
            foreach ($starter->actions as $action) {
                $items[] = ['type' => 'action', 'qualifier' => null, 'data' => self::shapeAction($action)];
            }
            foreach ($starter->abilities as $ability) {
                $items[] = ['type' => 'ability', 'qualifier' => null, 'data' => self::shapeAbility($ability)];
            }
        }

        foreach ($crew->crewCardAdvancements as $advancement) {
            $effect = $advancement->crewCardEffect;
            if (! $effect) {
                continue;
            }

            $isCrewUpgrade = $effect instanceof Upgrade;

            // Tier-4 grants ONE effect (pg 32: "'effect' refers to a single
            // ability, action, or trigger") — crew_card_item_type/_id pin
            // down which one. Only ever set on the Upgrade source; null there
            // means a legacy pre-granularity row, and CampaignCrewCard-source
            // rows are always null (that catalog stays whole-row — see the
            // migration/model docblocks) — both fall through to the original
            // whole-effect loop below.
            if ($isCrewUpgrade && $advancement->crew_card_item_type && $advancement->crew_card_item_id) {
                $picked = self::pickedItem($effect, $advancement->crew_card_item_type, $advancement->crew_card_item_id);
                if ($picked) {
                    $items[] = $picked;
                }

                continue;
            }

            foreach ($effect->actions as $action) {
                $restriction = $isCrewUpgrade ? $action->pivot->restriction : null; // @phpstan-ignore property.notFound (pivot from MorphToMany/BelongsToMany)
                $items[] = [
                    'type' => 'action',
                    'qualifier' => self::descriptorFor($restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Action),
                    'data' => self::shapeAction($action),
                ];
            }
            foreach ($effect->abilities as $ability) {
                $restriction = $isCrewUpgrade ? $ability->pivot->restriction : null; // @phpstan-ignore property.notFound (pivot from MorphToMany/BelongsToMany)
                $items[] = [
                    'type' => 'ability',
                    'qualifier' => self::descriptorFor($restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Ability),
                    'data' => self::shapeAbility($ability),
                ];
            }
            // Standalone triggers (not nested under one of the effect's own
            // actions) only exist on the real Upgrade catalog — CampaignCrewCard
            // has no top-level triggers() relation.
            if ($effect instanceof Upgrade) {
                foreach ($effect->triggers as $trigger) {
                    $restriction = $trigger->pivot->restriction; // @phpstan-ignore property.notFound (pivot from MorphToMany)
                    $items[] = [
                        'type' => 'trigger',
                        'qualifier' => self::descriptorFor($restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Trigger),
                        'data' => self::shapeTrigger($trigger),
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Resolves a single picked action/ability/trigger out of an already
     * loaded Upgrade's collections — an action brings its own nested
     * triggers along automatically (pg 32: "if an action is chosen, it will
     * automatically come with any associated triggers"), matching
     * shapeAction()'s own 'triggers' field.
     *
     * @return array{type: 'action'|'ability'|'trigger', qualifier: string|null, data: array<string, mixed>}|null
     */
    private static function pickedItem(Upgrade $effect, string $itemType, int $itemId): ?array
    {
        return match ($itemType) {
            'action' => ($action = $effect->actions->firstWhere('id', $itemId)) ? [
                'type' => 'action',
                'qualifier' => self::descriptorFor($action->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Action), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'data' => self::shapeAction($action),
            ] : null,
            'ability' => ($ability = $effect->abilities->firstWhere('id', $itemId)) ? [
                'type' => 'ability',
                'qualifier' => self::descriptorFor($ability->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Ability), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'data' => self::shapeAbility($ability),
            ] : null,
            'trigger' => ($trigger = $effect->triggers->firstWhere('id', $itemId)) ? [
                'type' => 'trigger',
                'qualifier' => self::descriptorFor($trigger->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Trigger), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'data' => self::shapeTrigger($trigger),
            ] : null,
            default => null,
        };
    }

    /**
     * The Arsenal Sheet's "View Card" text fallback (shown before the
     * generated combined image exists) needs one row per held Tier-4
     * advancement — a single picked item (pg 32) for a new-style
     * crew_upgrade pick, or the whole source card for a campaign_crew_card
     * pick / a legacy pre-granularity crew_upgrade row (no item to narrow to).
     *
     * @return array{id: int, name: string, body: string|null, front_image: string|null, actions: array<int, array<string, mixed>>, abilities: array<int, array<string, mixed>>, triggers: array<int, array<string, mixed>>}|null
     */
    public static function advancementEffectRow(CampaignCrewCardAdvancement $adv): ?array
    {
        $effect = $adv->crewCardEffect;
        if (! $effect) {
            return null;
        }

        if ($effect instanceof Upgrade && $adv->crew_card_item_type && $adv->crew_card_item_id) {
            return match ($adv->crew_card_item_type) {
                'action' => ($a = $effect->actions->firstWhere('id', $adv->crew_card_item_id)) ? [
                    'id' => $a->id, 'name' => $a->name, 'body' => $a->description, 'front_image' => null,
                    'actions' => [self::shapeAction($a)], 'abilities' => [], 'triggers' => [],
                ] : null,
                'ability' => ($a = $effect->abilities->firstWhere('id', $adv->crew_card_item_id)) ? [
                    'id' => $a->id, 'name' => $a->name, 'body' => $a->description, 'front_image' => null,
                    'actions' => [], 'abilities' => [self::shapeAbility($a)], 'triggers' => [],
                ] : null,
                'trigger' => ($t = $effect->triggers->firstWhere('id', $adv->crew_card_item_id)) ? [
                    'id' => $t->id, 'name' => $t->name, 'body' => $t->description, 'front_image' => null,
                    'actions' => [], 'abilities' => [], 'triggers' => [self::shapeTrigger($t)],
                ] : null,
                default => null,
            };
        }

        return array_merge($effect->toArray(), [
            'body' => $effect->description,
            'actions' => $effect->actions->map(fn (Action $a) => self::shapeAction($a))->all(),
            'abilities' => $effect->abilities->map(fn (Ability $a) => self::shapeAbility($a))->all(),
            'triggers' => [],
        ]);
    }

    /**
     * Eager-loads everything build() needs — call before passing a crew in.
     * crewCardAdvancements.crewCardEffect is polymorphic (CampaignCrewCard
     * or Upgrade), and only Upgrade has a top-level triggers() relation, so
     * the nested load must be type-specific via morphWith() rather than
     * plain dot notation (which would try — and fail — to call ->triggers()
     * on every resolved type uniformly).
     */
    public static function eagerLoad(CampaignCrew $crew): CampaignCrew
    {
        return $crew->load([
            'crewCardEffect.actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'crewCardEffect.abilities',
            'crewCardAdvancements.crewCardEffect' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    CampaignCrewCard::class => [
                        'actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                        'abilities',
                    ],
                    Upgrade::class => [
                        'actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
                        'abilities',
                        'triggers',
                    ],
                ]);
            },
        ]);
    }

    private static function descriptorFor(?string $restriction, CrewUpgradeRestrictionDescriptorTypeEnum $type): ?string
    {
        if ($restriction === null) {
            return null;
        }

        $enum = CrewUpgradeRestrictionEnum::tryFrom($restriction);

        return $enum?->descriptor($type);
    }

    /** @return array<string, mixed> */
    private static function shapeAction(Action $action): array
    {
        return [
            'id' => $action->id,
            'name' => $action->name,
            'type' => $action->type,
            'is_signature' => (bool) ($action->pivot->is_signature_action ?? false),
            'stone_cost' => $action->stone_cost,
            'range' => $action->range,
            'range_type' => $action->range_type,
            'stat' => $action->stat,
            'stat_suits' => $action->stat_suits,
            'stat_modifier' => $action->stat_modifier,
            'resisted_by' => $action->resisted_by,
            'target_number' => $action->target_number,
            'target_suits' => $action->target_suits,
            'damage' => $action->damage,
            'description' => $action->description,
            'triggers' => $action->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost,
                'description' => $t->description,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function shapeAbility(Ability $ability): array
    {
        return [
            'id' => $ability->id,
            'name' => $ability->name,
            'suits' => $ability->suits,
            'defensive_ability_type' => $ability->defensive_ability_type,
            'costs_stone' => $ability->costs_stone,
            'description' => $ability->description,
        ];
    }

    /** @return array<string, mixed> */
    private static function shapeTrigger(Trigger $trigger): array
    {
        return [
            'id' => $trigger->id,
            'name' => $trigger->name,
            'suits' => $trigger->suits,
            'stone_cost' => $trigger->stone_cost,
            'description' => $trigger->description,
        ];
    }
}
