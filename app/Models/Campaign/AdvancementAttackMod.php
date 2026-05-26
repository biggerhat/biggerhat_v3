<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\AdvancementAttackModFactory;

/**
 * Tier 1 Attack Modification advancement (pg 38–40). Flipped value gates
 * options ≤ value; mostly triggers, a few Skl boosts, one Signature row,
 * jokers add Cruel Lessons / Consult the Bones.
 */
class AdvancementAttackMod extends Advancement
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<AdvancementAttackModFactory> */
    protected $table = 'advancement_attack_mod';

    protected static function newFactory(): AdvancementAttackModFactory
    {
        return AdvancementAttackModFactory::new();
    }
}
