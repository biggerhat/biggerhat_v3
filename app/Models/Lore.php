<?php

namespace App\Models;

use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperLore
 */
class Lore extends Model
{
    /** @use HasFactory<\Database\Factories\LoreFactory> */
    use HasFactory;

    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];

    /** @return BelongsToMany<LoreMedia, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(LoreMedia::class, 'lore_lore_media');
    }

    /** @return BelongsToMany<Character, $this> */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'character_lore');
    }
}
