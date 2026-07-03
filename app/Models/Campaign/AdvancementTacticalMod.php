<?php

namespace App\Models\Campaign;

use App\Traits\Campaign\HasAttackTacticalAdvancementShape;
use Database\Factories\Campaign\AdvancementTacticalModFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tier 1 Tactical Modification advancement (pg 41–43). Flip value gates
 * options <= value; mostly triggers, a few skl_boost rows, one signature
 * row, Red/Black Joker rows grant specific named triggers.
 *
 * @mixin IdeHelperAdvancementTacticalMod
 */
class AdvancementTacticalMod extends Model
{
    use HasAttackTacticalAdvancementShape;

    /** @use HasFactory<AdvancementTacticalModFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory(): AdvancementTacticalModFactory
    {
        return AdvancementTacticalModFactory::new();
    }
}
