<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\AdvancementActionFactory;

/**
 * Tier 2 Action advancement (pg 44–49). Adds a new action to the leader.
 * Two "always available" rows (Tap the Leyline, Healing Energy) are
 * unconditionally selectable. Joker rows let the player pick any action
 * from a non-master/non-totem model sharing a keyword (cost ≤ 10).
 */
class AdvancementAction extends Advancement
{
    protected $table = 'advancement_action';

    protected static function newFactory(): AdvancementActionFactory
    {
        return AdvancementActionFactory::new();
    }
}
