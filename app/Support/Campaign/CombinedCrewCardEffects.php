<?php

namespace App\Support\Campaign;

use App\Enums\CrewUpgradeRestrictionDescriptorTypeEnum;
use App\Enums\CrewUpgradeRestrictionEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Models\Campaign\CampaignCrewCardAdvancement;
use App\Models\CustomUpgrade;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Builds the flat, ordered list of effects a crew's single generated Crew
 * Card image (CampaignCrew::crew_card_front_image) actually renders: the
 * starter effect's own actions/abilities, followed by every currently-held
 * Tier-4 borrow's actions/abilities/triggers, in acquisition order.
 *
 * Every action/ability/trigger item carries a `restriction` and a printed
 * `qualifier` (the qualifying text a real crew card prints above a
 * restricted effect, e.g. "Friendly Ten Thunders models gain the following
 * action:") via CrewUpgradeRestrictionEnum::descriptor() — a real Crew Card
 * Upgrade borrow (source 'crew_upgrade') uses its own stored pivot
 * restriction; the starter and any generic ('campaign_crew_card'-sourced)
 * borrow fall back to DEFAULT_RESTRICTION/BORROWED_RESTRICTION below, since
 * that catalog has no restriction field of its own. `qualifier_generic` is
 * the same text without naming a specific type, for when the face component
 * groups several same-restriction items under one shared header instead of
 * repeating the line per item.
 */
class CombinedCrewCardEffects
{
    /**
     * Every generic pg 15-16 Crew Card option (the starter, and any
     * 'campaign_crew_card'-sourced Tier-4 borrow) carries this same baseline
     * restriction in the rulebook's own text ("Non-peon models in this crew
     * with either of your chosen keywords gain the following...") — it's
     * never stored as data on that catalog (unlike a real Upgrade's per-item
     * `restriction` pivot), so callers needing the actual qualifying rule
     * (not just its printed text) must default to it explicitly.
     */
    public const DEFAULT_RESTRICTION = CrewUpgradeRestrictionEnum::FriendlyNonPeonKeyword;

    /**
     * Tier-4 Crew Card Advancement upgrades a borrowed generic
     * ('campaign_crew_card'-sourced) effect's restriction (pg 31-32): "The
     * effect comes with any limitations it had on the original crew card,
     * except that it will affect both of the crew's keywords instead of just
     * one" — the non-peon restriction carries over unchanged (confirmed by
     * the rulebook's own worked examples), only "either" keyword becomes
     * "both". Never applied to a real Upgrade-sourced ('crew_upgrade') borrow,
     * whose restriction is the specific pivot value stored on that card.
     */
    public const BORROWED_RESTRICTION = CrewUpgradeRestrictionEnum::FriendlyNonPeonBothKeywords;

    /**
     * @return array<int, array{type: 'action'|'ability'|'trigger'|'text'|'choice', qualifier: string|null, qualifier_generic: string|null, restriction: string|null, source: 'starter'|'borrowed', data: array<string, mixed>}>
     */
    public static function build(CampaignCrew $crew): array
    {
        $items = [];

        // `source` splits the combined card into two physical faces (T2-22):
        // starter effect on the front (CombinedCrewCardImageGenerator's
        // crew_card_front_image), every held Tier-4 borrow on the back
        // (crew_card_back_image) — see CombinedCrewCardFace's `side` prop.
        //
        // Once the player has saved an editable copy of their crew card via
        // "Edit Crew Card" (StartingArsenalController::saveCrewCardToCardCreator()
        // → a CustomUpgrade flagged is_campaign_crew_card), THAT edited copy
        // is the source of truth — not the immutable catalog row it started
        // from. Without this, edits made in the Card Creator editor never
        // showed up anywhere that represents "the crew's card" (not the
        // generated image, not the Arsenal Sheet's live view).
        $customCrewCard = CustomUpgrade::query()->where('campaign_crew_id', $crew->id)->where('is_campaign_crew_card', true)->first();

        if ($customCrewCard) {
            $starterName = $customCrewCard->display_name;
            foreach ($customCrewCard->content_blocks ?? [] as $i => $block) {
                $items[] = match ($block['type'] ?? null) {
                    'text' => ['type' => 'text', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'starter', 'data' => ['body' => $block['text'] ?? '']],
                    'action' => ['type' => 'action', 'qualifier' => self::DEFAULT_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Action), 'qualifier_generic' => self::DEFAULT_RESTRICTION->descriptorGeneric(), 'restriction' => self::DEFAULT_RESTRICTION->value, 'source' => 'starter', 'data' => self::shapeActionBlock($i, $block['data'] ?? [])],
                    'ability' => ['type' => 'ability', 'qualifier' => self::DEFAULT_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Ability), 'qualifier_generic' => self::DEFAULT_RESTRICTION->descriptorGeneric(), 'restriction' => self::DEFAULT_RESTRICTION->value, 'source' => 'starter', 'data' => self::shapeAbilityBlock($i, $block['data'] ?? [])],
                    default => null,
                };
            }
            $items = array_values(array_filter($items));
            if ($crew->crew_card_choice) {
                $items[] = ['type' => 'choice', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'starter', 'data' => [...$crew->crew_card_choice, 'effect_name' => $starterName]];
            }
        } elseif ($starter = $crew->crewCardEffect) {
            if ($starter->description) {
                $items[] = ['type' => 'text', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'starter', 'data' => ['body' => $starter->description]];
            }
            // The starter's own token/marker/upgrade-type pick (pg 17-18) —
            // previously only shown as page text next to the generated image,
            // never printed on the image itself (T3-33). effect_name lets the
            // face component print a top-of-card "Chosen for X:" note instead
            // of inlining the pick amongst the effect list.
            if ($crew->crew_card_choice) {
                $items[] = ['type' => 'choice', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'starter', 'data' => [...$crew->crew_card_choice, 'effect_name' => $starter->name]];
            }
            foreach ($starter->actions as $action) {
                $items[] = ['type' => 'action', 'qualifier' => self::DEFAULT_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Action), 'qualifier_generic' => self::DEFAULT_RESTRICTION->descriptorGeneric(), 'restriction' => self::DEFAULT_RESTRICTION->value, 'source' => 'starter', 'data' => self::shapeAction($action)];
            }
            foreach ($starter->abilities as $ability) {
                $items[] = ['type' => 'ability', 'qualifier' => self::DEFAULT_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Ability), 'qualifier_generic' => self::DEFAULT_RESTRICTION->descriptorGeneric(), 'restriction' => self::DEFAULT_RESTRICTION->value, 'source' => 'starter', 'data' => self::shapeAbility($ability)];
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
                    $items[] = [...$picked, 'source' => 'borrowed'];
                }

                continue;
            }

            // Whole-card borrow (campaign_crew_card source, or a legacy
            // pre-granularity crew_upgrade row) — mirrors the starter block
            // above: its own default/description text is a distinct printed
            // line on the card, not just a container for its actions/
            // abilities, so it needs the same 'text' item pushed here or a
            // second (or later) borrowed effect's description silently never
            // makes it onto the combined card image.
            if ($effect->description) {
                $items[] = ['type' => 'text', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'borrowed', 'data' => ['body' => $effect->description]];
            }

            // This specific borrow's own token/marker/upgrade-type pick, when
            // its generic catalog source (CampaignCrewCard) required one —
            // never set on an Upgrade-sourced borrow (T3-33).
            if ($advancement->crew_card_choice) {
                $items[] = ['type' => 'choice', 'qualifier' => null, 'qualifier_generic' => null, 'restriction' => null, 'source' => 'borrowed', 'data' => [...$advancement->crew_card_choice, 'effect_name' => $effect->name]];
            }

            foreach ($effect->actions as $action) {
                // @phpstan-ignore property.notFound (pivot from MorphToMany/BelongsToMany)
                $pivotRestriction = $isCrewUpgrade ? $action->pivot->restriction : null;
                $items[] = [
                    'type' => 'action',
                    // A real Upgrade borrow prints its own stored restriction;
                    // a generic-catalog borrow falls back to BORROWED_RESTRICTION
                    // (pg 31-32: upgrades to "both keywords" once borrowed).
                    'qualifier' => $isCrewUpgrade
                        ? self::descriptorFor($pivotRestriction, CrewUpgradeRestrictionDescriptorTypeEnum::Action)
                        : self::BORROWED_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Action),
                    'qualifier_generic' => $isCrewUpgrade
                        ? self::descriptorForGeneric($pivotRestriction)
                        : self::BORROWED_RESTRICTION->descriptorGeneric(),
                    'restriction' => $isCrewUpgrade ? $pivotRestriction : self::BORROWED_RESTRICTION->value,
                    'source' => 'borrowed',
                    'data' => self::shapeAction($action),
                ];
            }
            foreach ($effect->abilities as $ability) {
                // @phpstan-ignore property.notFound (pivot from MorphToMany/BelongsToMany)
                $pivotRestriction = $isCrewUpgrade ? $ability->pivot->restriction : null;
                $items[] = [
                    'type' => 'ability',
                    'qualifier' => $isCrewUpgrade
                        ? self::descriptorFor($pivotRestriction, CrewUpgradeRestrictionDescriptorTypeEnum::Ability)
                        : self::BORROWED_RESTRICTION->descriptor(CrewUpgradeRestrictionDescriptorTypeEnum::Ability),
                    'qualifier_generic' => $isCrewUpgrade
                        ? self::descriptorForGeneric($pivotRestriction)
                        : self::BORROWED_RESTRICTION->descriptorGeneric(),
                    'restriction' => $isCrewUpgrade ? $pivotRestriction : self::BORROWED_RESTRICTION->value,
                    'source' => 'borrowed',
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
                        'qualifier_generic' => self::descriptorForGeneric($restriction),
                        'restriction' => $restriction,
                        'source' => 'borrowed',
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
     * @return array{type: 'action'|'ability'|'trigger', qualifier: string|null, qualifier_generic: string|null, restriction: string|null, data: array<string, mixed>}|null
     */
    private static function pickedItem(Upgrade $effect, string $itemType, int $itemId): ?array
    {
        return match ($itemType) {
            'action' => ($action = $effect->actions->firstWhere('id', $itemId)) ? [
                'type' => 'action',
                'qualifier' => self::descriptorFor($action->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Action), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'qualifier_generic' => self::descriptorForGeneric($action->pivot->restriction), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'restriction' => $action->pivot->restriction, // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'data' => self::shapeAction($action),
            ] : null,
            'ability' => ($ability = $effect->abilities->firstWhere('id', $itemId)) ? [
                'type' => 'ability',
                'qualifier' => self::descriptorFor($ability->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Ability), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'qualifier_generic' => self::descriptorForGeneric($ability->pivot->restriction), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'restriction' => $ability->pivot->restriction, // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'data' => self::shapeAbility($ability),
            ] : null,
            'trigger' => ($trigger = $effect->triggers->firstWhere('id', $itemId)) ? [
                'type' => 'trigger',
                'qualifier' => self::descriptorFor($trigger->pivot->restriction, CrewUpgradeRestrictionDescriptorTypeEnum::Trigger), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'qualifier_generic' => self::descriptorForGeneric($trigger->pivot->restriction), // @phpstan-ignore property.notFound (pivot from MorphToMany)
                'restriction' => $trigger->pivot->restriction, // @phpstan-ignore property.notFound (pivot from MorphToMany)
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
                        'tokens:id,name,description,is_general',
                        'markers:id,name,description,base,is_general',
                    ],
                ]);
            },
        ]);
    }

    /**
     * Tokens/Markers relevant to the crew's back face (pg ...): a
     * quick-reference gathered from everything currently "in play" for this
     * crew, deduped by id across both sources:
     *
     *   - Every currently-active arsenal model's official Character: reads
     *     `Character::tokens()`/`markers()` (existing, admin-curated —
     *     populated by `php artisan app:link-tokens-and-markers`, which
     *     already scans this Character's actions/abilities/triggers'
     *     description text for token/marker references). Homebrew
     *     CustomCharacter hires contribute nothing — no such relation exists
     *     for them.
     *   - The crew's held Crew Card effects (starter + every Tier-4 borrow):
     *     only Upgrade-sourced effects carry tokens()/markers() (the same
     *     relation `app:link-tokens-and-markers` populates) — the generic
     *     pg 15-16 CampaignCrewCard catalog has no such link yet.
     *
     * Arsenal models are queried fresh rather than read off
     * `$crew->arsenalModels`: that relation is separately eager-loaded
     * elsewhere (ArsenalSheetController::render(), with a much richer
     * column/relation set for the rest of the Arsenal Sheet payload) before
     * this runs, and re-`load()`ing it here with a narrower column
     * selection would silently clobber that earlier load instead of adding
     * to it.
     *
     * @return array<int, array{type: 'token'|'marker', id: int, name: string, description: string|null, base: string|null}>
     */
    public static function arsenalTokensAndMarkers(CampaignCrew $crew): array
    {
        $tokens = collect();
        $markers = collect();

        $activeModels = CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->active()
            ->whereNotNull('character_id')
            ->with([
                'character:id',
                'character.tokens:id,name,description,is_general',
                'character.markers:id,name,description,base,is_general',
            ])
            ->get();

        foreach ($activeModels as $model) {
            if (! $model->character) {
                continue;
            }
            $tokens = $tokens->concat($model->character->tokens);
            $markers = $markers->concat($model->character->markers);
        }

        // Crew Card effects: starter + every held Tier-4 borrow. Reads the
        // same crewCardEffect/crewCardAdvancements relations build() uses
        // (see eagerLoad() above, which includes tokens()/markers() on the
        // Upgrade branch).
        $effects = collect([$crew->crewCardEffect])
            ->merge($crew->crewCardAdvancements->map(fn ($adv) => $adv->crewCardEffect))
            ->filter();

        foreach ($effects as $effect) {
            if ($effect instanceof Upgrade) {
                $tokens = $tokens->concat($effect->tokens);
                $markers = $markers->concat($effect->markers);
            }
        }

        // "General" tokens/markers (Focus, Shielded, Scheme, Strategy,
        // Remains, …) are universal Malifaux game pieces, not something this
        // specific crew grants — excluded from its own quick-reference even
        // when the heuristic linker (LinkTokensAndMarkers) attached one via
        // a text-mention match rather than an actual grant.
        $tokenItems = $tokens->reject(fn (Token $t) => $t->is_general)->unique('id')->sortBy('name')->values()->map(fn (Token $t) => [
            'type' => 'token', 'id' => $t->id, 'name' => $t->name, 'description' => $t->description, 'base' => null,
        ]);

        $markerItems = $markers->reject(fn (Marker $m) => $m->is_general)->unique('id')->sortBy('name')->values()->map(fn (Marker $m) => [
            'type' => 'marker', 'id' => $m->id, 'name' => $m->name, 'description' => $m->description, 'base' => (string) $m->base,
        ]);

        return $tokenItems->concat($markerItems)->all();
    }

    private static function descriptorFor(?string $restriction, CrewUpgradeRestrictionDescriptorTypeEnum $type): ?string
    {
        if ($restriction === null) {
            return null;
        }

        $enum = CrewUpgradeRestrictionEnum::tryFrom($restriction);

        return $enum?->descriptor($type);
    }

    private static function descriptorForGeneric(?string $restriction): ?string
    {
        if ($restriction === null) {
            return null;
        }

        $enum = CrewUpgradeRestrictionEnum::tryFrom($restriction);

        return $enum?->descriptorGeneric();
    }

    /**
     * Shapes an action block from a player-edited crew card's content_blocks
     * (see StartingArsenalController::saveCrewCardToCardCreator()) into the
     * same output shape as shapeAction() — content_blocks has no catalog id,
     * so `$i` (the block's position) stands in for it, and fields the Card
     * Creator editor doesn't capture (triggers) default empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function shapeActionBlock(int $i, array $data): array
    {
        return [
            'id' => $i,
            'name' => $data['name'] ?? '',
            'type' => $data['type'] ?? null,
            'is_signature' => false,
            'stone_cost' => $data['stone_cost'] ?? 0,
            'range' => $data['range'] ?? null,
            'range_type' => $data['range_type'] ?? null,
            'stat' => $data['stat'] ?? null,
            'stat_suits' => $data['stat_suits'] ?? null,
            'stat_modifier' => $data['stat_modifier'] ?? null,
            'resisted_by' => $data['resisted_by'] ?? null,
            'target_number' => $data['target_number'] ?? null,
            'target_suits' => $data['target_suits'] ?? null,
            'damage' => $data['damage'] ?? null,
            'description' => $data['description'] ?? null,
            'triggers' => $data['triggers'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function shapeAbilityBlock(int $i, array $data): array
    {
        return [
            'id' => $i,
            'name' => $data['name'] ?? '',
            'suits' => $data['suits'] ?? null,
            'defensive_ability_type' => $data['defensive_ability_type'] ?? null,
            'costs_stone' => (bool) ($data['costs_stone'] ?? false),
            'description' => $data['description'] ?? null,
        ];
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
