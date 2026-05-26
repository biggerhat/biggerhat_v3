<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\InjuryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for an injury suffered during Phase 6 of the Aftermath flow
 * (pg 34–35). Some rows reflip (Permanent Hex on no-trigger models,
 * Headstrong / Traitor on masters), some annihilate (Killed Off), Black Joker
 * swaps the model to the opposing crew, Red Joker reflips on Lucky Miss.
 */
class Injury extends Model
{
    /** @use HasFactory<InjuryFactory> */
    use HasFactory;

    protected $table = 'injury_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'reflip_if_no_triggers' => 'boolean',
            'reflip_if_master_or_totem' => 'boolean',
            'is_traitor' => 'boolean',
            'is_close_call' => 'boolean',
            'annihilates_model' => 'boolean',
        ];
    }

    protected static function newFactory(): InjuryFactory
    {
        return InjuryFactory::new();
    }
}
