<?php

namespace App\Models\Campaign;

use App\Models\Ability;
use Database\Factories\Campaign\LuckyMissFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catalog entry for the Lucky Miss table (pg 36) — positive upgrades flipped
 * when an injury/doctor result is a red joker. Any-joker → Doppelganger
 * (free copy in the arsenal).
 *
 * @property int|null $ability_id
 * @property-read Ability|null $ability
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

    /**
     * The real, already-existing Ability this result grants to the
     * affected unit — null for rows with no mechanical ability grant (just
     * flavor text).
     */
    public function ability(): BelongsTo
    {
        return $this->belongsTo(Ability::class);
    }
}
