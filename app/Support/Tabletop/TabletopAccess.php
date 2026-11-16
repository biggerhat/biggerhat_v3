<?php

namespace App\Support\Tabletop;

use App\Enums\PermissionEnum;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * Who may use the pre-release tabletop: everyone while the `experiment-tabletop` flag is on, and, while it is off, only people
 * holding the `use_tabletop_beta` permission (the super_admin role holds every permission). That lets specific users beta test it
 * without switching it on for the site.
 */
class TabletopAccess
{
    public const FLAG = 'experiment-tabletop';

    public static function allows(?User $user): bool
    {
        return Feature::for(null)->active(self::FLAG)
            || ($user !== null && $user->can(PermissionEnum::UseTabletopBeta->value));
    }
}
