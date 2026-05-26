<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\CrewCardEffectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for one of the 13 starter Crew Card effects (pg 15–16) plus
 * any later borrows referenced by Tier-4 advancements.
 */
class CrewCardEffect extends Model
{
    /** @use HasFactory<CrewCardEffectFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'requires_token_choice' => 'boolean',
            'requires_marker_choice' => 'boolean',
            'requires_upgrade_type_choice' => 'boolean',
            'restrictions' => 'array',
            'grants_ability' => 'array',
            'grants_action' => 'array',
        ];
    }

    protected static function newFactory(): CrewCardEffectFactory
    {
        return CrewCardEffectFactory::new();
    }
}
