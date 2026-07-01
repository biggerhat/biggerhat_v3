<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\AdvancementTableEnum;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignLeaderAdvancement;
use App\Models\CustomCharacter;
use App\Models\Trigger;

/**
 * Shared Leadership-Experience advancement engine (pg 31, 38-50). Both the
 * Aftermath's Advance-Leader step and the Arsenal Sheet's per-box logging use
 * this so the tier-gating / summoning-once / totem / flip-ceiling rules and the
 * record shape stay in one place.
 */
class LeaderAdvancementService
{
    /**
     * Returns an error string if any advancement breaks a rule, else null.
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    public function validate(CustomCharacter $leader, array $advancements): ?string
    {
        // XP-track box tiers keyed by index — the box reached caps tier (pg 31).
        $track = $leader->xp_track ?? CustomCharacter::defaultXpTrack();
        $boxTierByIndex = [];
        foreach ($track as $box) {
            $boxTierByIndex[$box['index']] = $box['tier'] ?? null;
        }

        $crewHasTotem = CustomCharacter::query()
            ->where('campaign_crew_id', $leader->campaign_crew_id)
            ->where('is_campaign_totem', true)
            ->where('current', true)
            ->exists();

        $sawSummoning = false;
        foreach ($advancements as $a) {
            $table = AdvancementTableEnum::tryFrom((string) ($a['source_table'] ?? ''));
            if (! $table) {
                return 'Unknown advancement table.';
            }

            // Tier gate against the XP box being filled.
            $position = $a['position_in_xp_track'] ?? null;
            $boxTier = $position !== null ? ($boxTierByIndex[$position] ?? null) : null;
            if ($boxTier === null) {
                return 'That experience box does not grant an advancement.';
            }
            if ($table->tier() > $boxTier) {
                return "A tier {$table->tier()} advancement cannot be taken at a tier {$boxTier} experience box.";
            }

            if ($table === AdvancementTableEnum::Summoning) {
                if ($sawSummoning) {
                    return 'Summoning Advancement may only be selected once.';
                }
                $sawSummoning = true;

                // Campaign-wide check across every prior aftermath on this leader.
                $existing = CampaignLeaderAdvancement::query()
                    ->where('custom_character_id', $leader->id)
                    ->where('source_table', AdvancementTableEnum::Summoning)
                    ->exists();
                if ($existing) {
                    return 'Summoning Advancement has already been used in this campaign.';
                }
            }

            if ($table === AdvancementTableEnum::Totem) {
                if ($crewHasTotem) {
                    return 'Totem Advancement is only available while the crew has no totem.';
                }

                $catalogId = $a['catalog_id'] ?? null;
                $flipValue = $a['flip_value'] ?? null;
                if ($catalogId === null || $flipValue === null) {
                    return 'Totem Advancement requires both a totem choice and the flipped value.';
                }
                $template = CustomCharacter::query()
                    ->where('is_campaign_totem_template', true)
                    ->whereKey($catalogId)
                    ->first();
                if (! $template) {
                    return 'Selected Totem is not a recognized template.';
                }
                if ((int) $template->campaign_totem_flip_value !== (int) $flipValue) {
                    return 'Totem Advancement requires an exact flip-value match — chosen totem does not match the flip.';
                }
            }

            // Flip-and-choose ceiling. Each table's catalog row carries its own
            // campaign_flip_value; the chosen option must be ≤ the flipped card.
            $catalogId = $a['catalog_id'] ?? null;
            $flip = $a['flip_value'] ?? null;
            if ($catalogId !== null && $flip !== null) {
                $rowFlip = match ($table) {
                    AdvancementTableEnum::AttackMod, AdvancementTableEnum::TacticalMod => Trigger::query()->whereKey($catalogId)->value('campaign_flip_value'),
                    AdvancementTableEnum::Action => Action::query()->whereKey($catalogId)->value('campaign_flip_value'),
                    AdvancementTableEnum::Ability => Ability::query()->whereKey($catalogId)->value('campaign_flip_value'),
                    default => null,
                };
                if ($rowFlip !== null && (int) $rowFlip > (int) $flip) {
                    return "That advancement needs a flip of {$rowFlip} or higher — your flip was {$flip}.";
                }
            }
        }

        return null;
    }

    /**
     * Persist the advancement records against the leader and apply the
     * mechanical effect to the leader's card data (actions/abilities JSON).
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    public function create(CustomCharacter $leader, array $advancements, ?int $sourceAftermathId): void
    {
        foreach ($advancements as $a) {
            $table = AdvancementTableEnum::from($a['source_table']);

            CampaignLeaderAdvancement::create([
                'custom_character_id' => $leader->id,
                'source_aftermath_id' => $sourceAftermathId,
                'source_table' => $a['source_table'],
                'catalog_core_id' => $a['catalog_id'] ?? null,
                'from_equipment_id' => $a['from_equipment_id'] ?? null,
                'applied_to_action_index' => $a['applied_to_action_index'] ?? -1,
                'position_in_xp_track' => $a['position_in_xp_track'],
                'free_choice' => $a['free_choice'] ?? null,
                'acquired_at' => now(),
            ]);

            $catalogId = isset($a['catalog_id']) ? (int) $a['catalog_id'] : null;

            match ($table) {
                AdvancementTableEnum::Totem => $this->createTotemFromTemplate(
                    $leader,
                    $catalogId ?? 0,
                    $a['totem_name'] ?? null,
                    isset($a['totem_size']) ? (int) $a['totem_size'] : null,
                    $a['totem_base'] ?? null,
                ),
                AdvancementTableEnum::Action, AdvancementTableEnum::Summoning => $catalogId
                    ? $this->applyActionToLeader($leader, $catalogId)
                    : null,
                AdvancementTableEnum::Ability => $catalogId
                    ? $this->applyAbilityToLeader($leader, $catalogId)
                    : null,
                AdvancementTableEnum::AttackMod, AdvancementTableEnum::TacticalMod => $catalogId
                    ? $this->applyTriggerToLeader($leader, $catalogId, (int) ($a['applied_to_action_index'] ?? -1))
                    : null,
                default => null,
            };
        }
    }

    private function applyActionToLeader(CustomCharacter $leader, int $actionId): void
    {
        $action = Action::with('triggers:id,name,suits,stone_cost,description')->find($actionId);
        if (! $action) {
            return;
        }

        $typeValue = $action->type instanceof \BackedEnum ? $action->type->value : (string) $action->type;
        $actions = $leader->actions ?? [];
        $actions[] = [
            'name' => $action->name,
            'type' => $typeValue,
            'category' => $typeValue,
            'is_signature' => false,
            'stone_cost' => $action->stone_cost ?? 0,
            'range' => $action->range,
            'range_type' => $action->range_type instanceof \BackedEnum ? $action->range_type->value : $action->range_type,
            'stat' => $action->stat,
            'stat_suits' => $action->stat_suits,
            'stat_modifier' => $action->stat_modifier instanceof \BackedEnum ? $action->stat_modifier->value : $action->stat_modifier,
            'resisted_by' => $action->resisted_by,
            'target_number' => $action->target_number,
            'target_suits' => $action->target_suits,
            'damage' => $action->damage,
            'description' => $action->description,
            'source_id' => $action->id,
            'triggers' => $action->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
                'source_id' => $t->id,
            ])->all(),
        ];
        $leader->actions = $actions;
        $leader->save();
    }

    private function applyAbilityToLeader(CustomCharacter $leader, int $abilityId): void
    {
        $ability = Ability::find($abilityId);
        if (! $ability) {
            return;
        }

        $abilities = $leader->abilities ?? [];
        $abilities[] = [
            'name' => $ability->name,
            'suits' => $ability->suits,
            'defensive_ability_type' => $ability->defensive_ability_type instanceof \BackedEnum
                ? $ability->defensive_ability_type->value
                : $ability->defensive_ability_type,
            'costs_stone' => (bool) $ability->costs_stone,
            'description' => $ability->description,
            'source_id' => $ability->id,
        ];
        $leader->abilities = $abilities;
        $leader->save();
    }

    private function applyTriggerToLeader(CustomCharacter $leader, int $triggerId, int $actionIndex): void
    {
        if ($actionIndex < 0) {
            return;
        }

        $trigger = Trigger::find($triggerId);
        if (! $trigger) {
            return;
        }

        $actions = $leader->actions ?? [];
        if (! isset($actions[$actionIndex])) {
            return;
        }

        $actions[$actionIndex]['triggers'][] = [
            'name' => $trigger->name,
            'suits' => $trigger->suits,
            'stone_cost' => $trigger->stone_cost ?? 0,
            'description' => $trigger->description,
            'source_id' => $trigger->id,
        ];
        $leader->actions = $actions;
        $leader->save();
    }

    private function createTotemFromTemplate(
        CustomCharacter $leader,
        int $templateId,
        ?string $totemName,
        ?int $totemSize,
        ?string $totemBase,
    ): void {
        $template = CustomCharacter::query()
            ->where('is_campaign_totem_template', true)
            ->whereKey($templateId)
            ->with(['campaignTotemActions.triggers', 'campaignTotemAbilities'])
            ->first();

        if (! $template) {
            return;
        }

        $actions = $template->campaignTotemActions->map(fn (Action $action) => [
            'name' => $action->name,
            'type' => $action->type ?? 'tactical',
            'category' => $action->type ?? 'tactical',
            'is_signature' => (bool) ($action->pivot->is_signature_action ?? false),
            'stone_cost' => $action->stone_cost ?? 0,
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
            'source_id' => $action->id,
            'triggers' => $action->triggers->map(fn (Trigger $t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost ?? 0,
                'description' => $t->description,
            ])->all(),
        ])->all();

        $abilities = $template->campaignTotemAbilities->map(fn (Ability $ab) => [
            'name' => $ab->name,
            'body' => $ab->description,
            'suits' => $ab->suits,
            'source_id' => $ab->id,
        ])->all();

        // BaseSizeEnum is int-backed (30/40/50); convert the user's label string,
        // falling back to the template's own base if the player didn't specify one.
        $baseInt = match ($totemBase) {
            '40mm' => 40,
            '50mm' => 50,
            '30mm' => 30,
            default => $template->base instanceof \BackedEnum
                ? (int) $template->base->value
                : (int) ($template->base ?? 30),
        };

        CustomCharacter::create([
            'user_id' => $leader->user_id,
            'campaign_crew_id' => $leader->campaign_crew_id,
            'is_campaign_leader' => false,
            'is_campaign_totem' => true,
            'current' => true,
            'name' => $totemName ?: $template->name,
            'faction' => $leader->faction,
            'station' => null,
            'cost' => null,
            'health' => $template->health ?? 0,
            'defense' => $template->defense ?? 0,
            'defense_suit' => $template->defense_suit instanceof \BackedEnum
                ? $template->defense_suit->value
                : $template->defense_suit,
            'willpower' => $template->willpower ?? 0,
            'willpower_suit' => $template->willpower_suit instanceof \BackedEnum
                ? $template->willpower_suit->value
                : $template->willpower_suit,
            'speed' => $template->speed ?? 0,
            'size' => $totemSize ?? $template->size ?? 1,
            'base' => $baseInt,
            'is_unhirable' => true,
            'actions' => $actions,
            'abilities' => $abilities,
            'keywords' => [],
            'characteristics' => [],
        ]);
    }
}
