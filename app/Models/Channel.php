<?php

namespace App\Models;

use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperChannel
 */
class Channel extends Model
{
    use HasFactory;
    use UsesSlugName;

    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! array_key_exists('image', $this->attributes)) {
            return null;
        }

        return $this->attributes['image'] ? '/storage/'.$this->attributes['image'] : null;
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** @return HasMany<Transmission, $this> */
    public function transmissions(): HasMany
    {
        return $this->hasMany(Transmission::class);
    }
}
