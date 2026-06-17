<?php

namespace App\Enums\Campaign;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * The five Leader archetypes for M4E Campaign Mode (Index of the Untold, pg 17).
 * Each archetype fixes the Leader's base Df/Wp/Sp/Health and constrains what
 * actions/abilities can be picked during Leader Build. Stat baselines and cost
 * caps live on the enum directly (FactionEnum-style) — there is no catalog
 * table; this enum is the single source of truth.
 */
enum LeaderArchetypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case LuckyUpstart = 'lucky_upstart';
    case Generalist = 'generalist';
    case HeavyHitter = 'heavy_hitter';
    case Schemer = 'schemer';
    case TalentedIndividual = 'talented_individual';

    public function df(): int
    {
        return match ($this) {
            self::Generalist, self::TalentedIndividual => 5,
            default => 6,
        };
    }

    public function wp(): int
    {
        return match ($this) {
            self::HeavyHitter => 4,
            self::LuckyUpstart => 6,
            default => 5,
        };
    }

    public function sp(): int
    {
        return match ($this) {
            self::Schemer => 7,
            self::TalentedIndividual => 5,
            default => 6,
        };
    }

    public function health(): int
    {
        return match ($this) {
            self::Schemer, self::TalentedIndividual => 13,
            default => 14,
        };
    }

    public function attackActionsCount(): int
    {
        return 1;
    }

    public function attackActionCostCap(): int
    {
        return match ($this) {
            self::HeavyHitter => 10,
            self::Generalist => 7,
            self::Schemer => 5,
            default => 6,
        };
    }

    /**
     * Heavy Hitter only — the chosen attack action also comes with one of
     * its source triggers, free.
     */
    public function attackGetsTrigger(): bool
    {
        return $this === self::HeavyHitter;
    }

    public function tacticalActionsCount(): int
    {
        return match ($this) {
            self::Schemer => 2,
            self::LuckyUpstart => 0,
            default => 1,
        };
    }

    public function tacticalActionCostCap(): int
    {
        return match ($this) {
            self::Schemer => 8,
            self::HeavyHitter => 5,
            self::Generalist => 7,
            default => 6,
        };
    }

    public function abilitiesCount(): int
    {
        return match ($this) {
            self::TalentedIndividual => 2,
            self::HeavyHitter => 0,
            default => 1,
        };
    }

    public function abilityCostCap(): int
    {
        return match ($this) {
            self::Schemer, self::TalentedIndividual => 8,
            self::Generalist => 7,
            default => 6,
        };
    }

    public function specialNotes(): ?string
    {
        return match ($this) {
            self::LuckyUpstart => 'Starts with a free equipment item rolled on creation.',
            default => null,
        };
    }

    /**
     * Wire shape consumed by `Campaigns/LeaderBuilder.vue`. Matches the legacy
     * `leader_archetypes` table row shape so the page didn't have to change.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function dataset(): array
    {
        return collect(self::cases())
            ->map(fn (self $a) => [
                'slug' => $a->value,
                'name' => $a->label(),
                'df' => $a->df(),
                'wp' => $a->wp(),
                'sp' => $a->sp(),
                'health' => $a->health(),
                'attack_actions_count' => $a->attackActionsCount(),
                'attack_action_cost_cap' => $a->attackActionCostCap(),
                'attack_gets_trigger' => $a->attackGetsTrigger(),
                'tactical_actions_count' => $a->tacticalActionsCount(),
                'tactical_action_cost_cap' => $a->tacticalActionCostCap(),
                'abilities_count' => $a->abilitiesCount(),
                'ability_cost_cap' => $a->abilityCostCap(),
                'special_notes' => $a->specialNotes(),
            ])
            ->values()
            ->all();
    }
}
