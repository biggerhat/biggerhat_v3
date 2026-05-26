<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wishlist;

/**
 * Ownership-based authorization for personal Wishlists.
 *
 *   - view: owner OR public flag set. Anonymous traffic with a share_code is
 *     handled via `viewShare` so anonymous access only ever works through that
 *     path.
 *   - update / delete / addItem / removeItem / addKeyword / togglePublic:
 *     owner only. super_admin bypasses for moderation.
 *
 * Replaces 10+ `abort_unless(Auth::id() === $wishlist->user_id, 403)` checks
 * scattered through WishlistController.
 */
class WishlistPolicy
{
    public function view(User $user, Wishlist $wishlist): bool
    {
        return $wishlist->user_id === $user->id
            || $wishlist->is_public
            || $user->hasRole('super_admin');
    }

    public function update(User $user, Wishlist $wishlist): bool
    {
        return $wishlist->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function delete(User $user, Wishlist $wishlist): bool
    {
        return $this->update($user, $wishlist);
    }

    public function viewShare(?User $user, Wishlist $wishlist): bool
    {
        if ($wishlist->is_public) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $wishlist->user_id === $user->id
            || $user->hasRole('super_admin');
    }
}
