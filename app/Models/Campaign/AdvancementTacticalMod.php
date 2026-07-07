<?php

namespace App\Models\Campaign;

use App\Traits\Campaign\HasAttackTacticalAdvancementShape;
use Database\Factories\Campaign\AdvancementTacticalModFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tier 1 Tactical Modification advancement (pg 41–43). Flip value gates
 * options <= value; mostly triggers, a few skl_boost rows, one signature
 * row. Unlike Attack Mod, the two Joker rows are color-specific — Red
 * Joker grants Illumination of Illios, Black Joker grants Darkness of
 * Delios — each with exactly one of is_black_joker/is_red_joker true.
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
