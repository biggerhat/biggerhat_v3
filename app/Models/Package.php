<?php

namespace App\Models;

use App\Enums\PackageCategoryEnum;
use App\Traits\UsesMiniatures;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
            'category' => PackageCategoryEnum::class,
            'factions' => 'array',
            'is_preassembled' => 'boolean',
            'released_at' => 'date',
        ];
    }

    /** @return MorphToMany<Character, $this> */
    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'packageable')->withPivot('quantity');
    }

    /** @return MorphToMany<Keyword, $this> */
    public function keywords(): MorphToMany
    {
        return $this->morphedByMany(Keyword::class, 'packageable');
    }

    /** @return MorphToMany<Blueprint, $this> */
    public function blueprints(): MorphToMany
    {
        return $this->morphedByMany(Blueprint::class, 'packageable');
    }

    /** @return MorphToMany<\App\Models\TOS\Unit, $this> */
    public function tosUnits(): MorphToMany
    {
        return $this->morphedByMany(\App\Models\TOS\Unit::class, 'packageable')->withPivot('quantity');
    }

    /** @return HasMany<PackageStoreLink, $this> */
    public function storeLinks(): HasMany
    {
        return $this->hasMany(PackageStoreLink::class)->orderBy('sort_order');
    }
}
