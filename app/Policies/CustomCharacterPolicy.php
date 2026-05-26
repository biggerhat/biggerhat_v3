<?php

namespace App\Policies;

use App\Models\CustomCharacter;
use App\Models\User;

/**
 * Ownership-based authorization for Custom Cards (user-created characters).
 *
 *   - view: owner only. Public share is a separate route keyed by `share_code`
 *     and bypasses this policy entirely.
 *   - update / delete: owner only. super_admin bypasses for moderation work.
 *
 * Replaces the ad-hoc `Auth::id() !== $customCharacter->user_id` checks that
 * were copy-pasted across CustomCharacterController.
 */
class CustomCharacterPolicy
{
    public function view(User $user, CustomCharacter $customCharacter): bool
    {
        return $customCharacter->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function update(User $user, CustomCharacter $customCharacter): bool
    {
        return $customCharacter->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function delete(User $user, CustomCharacter $customCharacter): bool
    {
        return $this->update($user, $customCharacter);
    }
}
