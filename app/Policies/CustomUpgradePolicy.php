<?php

namespace App\Policies;

use App\Models\CustomUpgrade;
use App\Models\User;

/**
 * Ownership-based authorization for Custom Upgrades (user-created upgrades).
 * Mirrors CustomCharacterPolicy — owner-or-super_admin for view/update/delete.
 * Public share lives on a separate share_code route and bypasses this policy.
 */
class CustomUpgradePolicy
{
    public function view(User $user, CustomUpgrade $customUpgrade): bool
    {
        return $customUpgrade->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function update(User $user, CustomUpgrade $customUpgrade): bool
    {
        return $customUpgrade->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function delete(User $user, CustomUpgrade $customUpgrade): bool
    {
        return $this->update($user, $customUpgrade);
    }
}
