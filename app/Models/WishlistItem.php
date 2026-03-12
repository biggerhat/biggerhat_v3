<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperWishlistItem
 */
class WishlistItem extends Model
{
    protected $guarded = ['id'];

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }

    public function wishlistable(): MorphTo
    {
        return $this->morphTo();
    }
}
