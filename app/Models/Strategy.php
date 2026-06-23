<?php

namespace App\Models;

use App\Enums\PoolSeasonEnum;
use App\Enums\SuitEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperStrategy
 */
class Strategy extends Model
{
    /** @use HasFactory<\Database\Factories\StrategyFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'season' => PoolSeasonEnum::class,
            'suit' => SuitEnum::class,
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

    /** Tokens this Strategy introduces (e.g. Plant Explosives → Explosive). */
    public function tokens(): BelongsToMany
    {
        return $this->belongsToMany(Token::class, 'strategy_token');
    }
}
