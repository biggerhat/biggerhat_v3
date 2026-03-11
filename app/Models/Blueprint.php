<?php

namespace App\Models;

use App\Enums\SculptVersionEnum;
use App\Traits\UsesCharacters;
use App\Traits\UsesMiniatures;
use App\Traits\UsesPackages;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperBlueprint
 */
class Blueprint extends Model
{
    /** @use HasFactory<\Database\Factories\BlueprintFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsesCharacters;
    use UsesMiniatures;
    use UsesPackages;
    use UsesSlugName;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'images' => 'array',
            'sculpt_version' => SculptVersionEnum::class,
            'published_at' => 'date',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithImages(Builder $query): Builder
    {
        return $query->whereJsonLength('images', '>', 0);
    }
}
