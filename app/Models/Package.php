<?php

namespace App\Models;

use App\Traits\UsesMiniatures;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @mixin IdeHelperPackage
 */
class Package extends Model
{
    /** @use HasFactory<\Database\Factories\PackageFactory> */
    use HasFactory;

    use UsesMiniatures;
    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'factions' => 'array',
            'is_preassembled' => 'boolean',
            'released_at' => 'date',
        ];
    }

    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'packageable');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'packageable');
    }
}
