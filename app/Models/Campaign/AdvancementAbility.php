<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\AdvancementAbilityFactory;

/**
 * Tier 2 Ability advancement (pg 50–51). Adds a new ability to the leader.
 * Three "always available" rows (Escape Path, Double Tap, Ethereal
 * Protection). Joker rows mirror the action table's free-pick mechanic.
 */
class AdvancementAbility extends Advancement
{
    protected $table = 'advancement_ability';

    protected static function newFactory(): AdvancementAbilityFactory
    {
        return AdvancementAbilityFactory::new();
    }
}
