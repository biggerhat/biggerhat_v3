<?php

namespace App\Models;

use App\Enums\PoolSeasonEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperScheme
 */
class Scheme extends Model
{
    /** @use HasFactory<\Database\Factories\SchemeFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'season' => PoolSeasonEnum::class,
        ];
    }

    public function scopeForSeason(Builder $query, PoolSeasonEnum $season): Builder
    {
        return $query->where('season', $season);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? "/storage/{$this->image}" : null;
    }

    /** @return BelongsTo<Scheme, $this> */
    public function nextSchemeOne(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'next_scheme_one_id');
    }

    /** @return BelongsTo<Scheme, $this> */
    public function nextSchemeTwo(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'next_scheme_two_id');
    }

    /** @return BelongsTo<Scheme, $this> */
    public function nextSchemeThree(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'next_scheme_three_id');
    }
}
