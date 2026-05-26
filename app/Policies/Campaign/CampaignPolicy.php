<?php

namespace App\Policies\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Models\Campaign\Campaign;
use App\Models\User;

/**
 * Authorization for Campaign actions. The feature-flag gate runs ahead of
 * these checks (`campaign.access` middleware); this layer enforces
 * organizer-vs-player permissions within an accessible campaign.
 */
class CampaignPolicy
{
    /**
     * Anyone can list their own campaigns; the controller filters the result
     * set, so an unconditional true is correct here.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Viewing a specific campaign requires membership. Super_admin bypasses.
     */
    public function view(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $campaign->players()->where('user_id', $user->id)->exists();
    }

    /**
     * Anyone allowed to use Campaign mode can create one — feature gate has
     * already enforced that.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Mutating settings, inviting, starting, ending — all organizer-only.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $campaign->players()
            ->where('user_id', $user->id)
            ->where('role', CampaignPlayerRoleEnum::Organizer)
            ->exists();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }
}
