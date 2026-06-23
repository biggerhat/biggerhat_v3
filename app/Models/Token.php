<?php

namespace App\Models;

use App\Enums\TokenRemovalTimingEnum;
use App\Traits\UsesCharacters;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperToken
 */
class Token extends Model
{
    /** @use HasFactory<\Database\Factories\TokenFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSelectOptionsScope;
    use UsesSlugName;
    use UsesUpgrades;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'removal_timing' => TokenRemovalTimingEnum::class,
            'is_general' => 'boolean',
        ];
    }

    /** Strategies that grant/use this token (e.g. Plant Explosives → Explosive). */
    public function strategies(): BelongsToMany
    {
        return $this->belongsToMany(Strategy::class, 'strategy_token');
    }

    /** "General" tokens (Focus, Shielded, Impact, …) shown in every crew's references. */
    public function scopeGeneral(Builder $query): void
    {
        $query->where('is_general', true);
    }
}
