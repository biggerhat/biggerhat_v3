<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\LuckyMissFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for the Lucky Miss table (pg 36) — positive upgrades flipped
 * when an injury/doctor result is a red joker. Any-joker → Doppelganger
 * (free copy in the arsenal).
 *
 * @mixin IdeHelperLuckyMiss
 */
class LuckyMiss extends Model
{
    /** @use HasFactory<LuckyMissFactory> */
    use HasFactory;

    protected $table = 'lucky_miss_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_doppelganger' => 'boolean',
        ];
    }

    protected static function newFactory(): LuckyMissFactory
    {
        return LuckyMissFactory::new();
    }
}
