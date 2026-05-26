<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\AdvancementTacticalModFactory;

/**
 * Tier 1 Tactical Modification advancement (pg 41–43). Same shape as
 * AdvancementAttackMod; targets a tactical action instead of an attack.
 */
class AdvancementTacticalMod extends Advancement
{
    protected $table = 'advancement_tactical_mod';

    protected static function newFactory(): AdvancementTacticalModFactory
    {
        return AdvancementTacticalModFactory::new();
    }
}
