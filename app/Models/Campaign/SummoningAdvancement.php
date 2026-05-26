<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\SummoningAdvancementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tier-3 Summoning advancement (pg 54). 7 entries; no flip — the player
 * picks one freely. Limited to once per leader.
 */
class SummoningAdvancement extends Model
{
    /** @use HasFactory<SummoningAdvancementFactory> */
    use HasFactory;

    protected $table = 'summoning_advancement_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'stat_block' => 'array',
        ];
    }

    protected static function newFactory(): SummoningAdvancementFactory
    {
        return SummoningAdvancementFactory::new();
    }
}
