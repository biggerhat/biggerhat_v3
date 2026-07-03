<?php

namespace App\Models\Campaign;

use App\Traits\Campaign\HasAttackTacticalAdvancementShape;
use Database\Factories\Campaign\AdvancementAttackModFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tier 1 Attack Modification advancement (pg 38–40). Flip value gates
 * options <= value; mostly triggers, a few skl_boost rows, one signature
 * row, Red/Black Joker rows grant specific named triggers.
 *
 * @mixin IdeHelperAdvancementAttackMod
 */
class AdvancementAttackMod extends Model
{
    use HasAttackTacticalAdvancementShape;

    /** @use HasFactory<AdvancementAttackModFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): AdvancementAttackModFactory
    {
        return AdvancementAttackModFactory::new();
    }
}
