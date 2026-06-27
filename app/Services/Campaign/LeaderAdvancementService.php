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
     * Persist the advancement records against the leader.
     *
     * @param  array<int, array<string, mixed>>  $advancements
     */
    public function create(CustomCharacter $leader, array $advancements, ?int $sourceAftermathId): void
    {
        foreach ($advancements as $a) {
            CampaignLeaderAdvancement::create([
                'custom_character_id' => $leader->id,
                'source_aftermath_id' => $sourceAftermathId,
                'source_table' => $a['source_table'],
                // Post-consolidation FK to core tables (upgrades / actions /
                // triggers / abilities / custom_characters depending on
                // source_table). Legacy catalog_id stays null on new rows.
                'catalog_core_id' => $a['catalog_id'] ?? null,
                'from_equipment_id' => $a['from_equipment_id'] ?? null,
                'applied_to_action_index' => $a['applied_to_action_index'] ?? -1,
                'position_in_xp_track' => $a['position_in_xp_track'],
                'free_choice' => $a['free_choice'] ?? null,
                'acquired_at' => now(),
            ]);
        }
    }
}
