<?php

namespace App\Support\Campaign;

use App\Enums\CrewUpgradeRestrictionDescriptorTypeEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
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
            'name' => $trigger->name,
            'suits' => $trigger->suits,
            'stone_cost' => $trigger->stone_cost,
            'description' => $trigger->description,
        ];
    }
}
