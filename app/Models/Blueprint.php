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
            'sculpt_version' => SculptVersionEnum::class,
            'published_at' => 'date',
        ];
    }

    /**
     * Scope to only blueprints that have an image.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithImage(Builder $query): Builder
    {
        return $query->whereNotNull('image_path');
    }
}
