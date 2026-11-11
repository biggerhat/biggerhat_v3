<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/** The five enemy roles of Cooperative Mode (Index of the Untold, pg 66). Encounters activate roles, not models. */
enum CoopRoleEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Obstructor = 'obstructor';
    case Brawler = 'brawler';
    case Firepower = 'firepower';
    case Support = 'support';
    case Boss = 'boss';
}
