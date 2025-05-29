<?php

namespace App\Models;

use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperTerrain
 */
class Terrain extends Model
{
    /** @use HasFactory<\Database\Factories\TerrainFactory> */
    use HasFactory;

    use UsesSlugName;

    protected $guarded = ['id'];

    public function markers(): BelongsToMany
    {
        return $this->belongsToMany(Marker::class, 'marker_terrain');
    }
}
