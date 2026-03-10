<?php

namespace App\Models;

use App\Enums\LoreMediaTypeEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperLoreMedia
 */
class LoreMedia extends Model
{
    /** @use HasFactory<\Database\Factories\LoreMediaFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $table = 'lore_media';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'type' => LoreMediaTypeEnum::class,
        ];
    }

    /** @return HasMany<Lore, $this> */
    public function lores(): HasMany
    {
        return $this->hasMany(Lore::class);
    }
}
