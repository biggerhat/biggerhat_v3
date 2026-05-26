<?php

namespace App\Support;

use App\Enums\PermissionEnum;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * Single source of truth for "can this user see Campaign mode?". Layered gate:
 *
 *   1. Global Pennant flag `m4e-campaign-mode` — when enabled, everyone passes.
 *   2. Per-user `use_campaign_mode` permission — playtester preview while the
 *      flag is off.
 *   3. `super_admin` role — bypasses everything.
 *
 * Public read paths (e.g. share-link Arsenal Sheets) accept a null user and
 * resolve solely off the global flag. Gate consumers (middleware, Inertia
 * share, sidebar nav) all call this so the rule stays in one place.
 */
class CampaignAccess
{
    /**
     * Returns true when the user (or anonymous visitor) should see Campaign UI.
     */
    public static function canUse(?User $user): bool
    {
        if (Feature::active('m4e-campaign-mode')) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can(PermissionEnum::UseCampaignMode->value);
    }
}
