<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Which advancement table a Leader spent an XP point against. Tiers gate which
 * tables are eligible based on the numbered XP box reached (Tier 1 boxes ≥ 1,
 * Tier 2 boxes ≥ 2, etc.). The Totem and Summoning tables have unique
 * mechanics — see Phase 4 of the Aftermath wizard.
 */
enum AdvancementTableEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case AttackMod = 'attack_mod';          // Tier 1 — modifies one attack action
    case TacticalMod = 'tactical_mod';      // Tier 1 — modifies one tactical action
    case Action = 'action';                 // Tier 2 — adds a new action
    case Ability = 'ability';               // Tier 2 — adds a new ability
    case Totem = 'totem';                   // Tier 3 — exact-match flip, unlocks totem
    case Summoning = 'summoning';           // Tier 3 — free choice, once only
    case CrewCard = 'crew_card';            // Tier 4 — adds an effect to the crew card

    public function tier(): int
    {
        return match ($this) {
            self::AttackMod, self::TacticalMod => 1,
            self::Action, self::Ability => 2,
            self::Totem, self::Summoning => 3,
            self::CrewCard => 4,
        };
    }
}
