<?php

namespace App\Models;

use App\Enums\LoreMediaTypeEnum;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /** @return BelongsToMany<Lore, $this> */
    public function lores(): BelongsToMany
    {
        return $this->belongsToMany(Lore::class, 'lore_lore_media');
    }
}
