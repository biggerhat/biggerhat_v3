<?php

namespace App\Policies;

use App\Models\CrewBuild;
use App\Models\User;

/**
 * Ownership-based authorization for saved Crew Builder records.
 *
 *   - view: owner only. Public viewing via share_code goes through `viewShare`
 *     (which honors the `is_public` flag) — keep them separate so a private
 *     build can never leak through a public share link.
 *   - update / delete: owner only. super_admin bypasses for moderation.
 *   - viewShare: anyone if the build is public; falls back to owner.
 */
class CrewBuildPolicy
{
    public function view(User $user, CrewBuild $crewBuild): bool
    {
        return $crewBuild->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function update(User $user, CrewBuild $crewBuild): bool
    {
        return $crewBuild->user_id === $user->id
            || $user->hasRole('super_admin');
    }

    public function delete(User $user, CrewBuild $crewBuild): bool
    {
        return $this->update($user, $crewBuild);
    }

    /**
     * Public share access — anyone may view if the build is flagged public,
     * otherwise the owner (or super_admin) only. Used by the share+quickRef
     * routes which accept anonymous traffic. Pass `null` for anonymous.
     */
    public function viewShare(?User $user, CrewBuild $crewBuild): bool
    {
        if ($crewBuild->is_public) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $crewBuild->user_id === $user->id
            || $user->hasRole('super_admin');
    }
}
