<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use App\Traits\UsesUpgrades;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Marker extends Model
{
    /** @use HasFactory<\Database\Factories\MarkerFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSelectOptionsScope;
    use UsesSlugName;
    use UsesUpgrades;

    protected $guarded = ['id'];

    public function terrains(): BelongsToMany
    {
        return $this->belongsToMany(Terrain::class, 'marker_terrain');
    }
}
