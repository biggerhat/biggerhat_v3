<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperMarker
 */
class Marker extends Model
{
    /** @use HasFactory<\Database\Factories\MarkerFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSelectOptionsScope;
    use UsesSlugName;
    use UsesUpgrades;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_general' => 'boolean',
        ];
    }

    public function terrains(): BelongsToMany
    {
        return $this->belongsToMany(Terrain::class, 'marker_terrain');
    }

    /** "General" markers (Scheme, Strategy, Remains, …) shown in every crew's references — mirrors Token::scopeGeneral(). */
    public function scopeGeneral(Builder $query): void
    {
        $query->where('is_general', true);
    }
}
