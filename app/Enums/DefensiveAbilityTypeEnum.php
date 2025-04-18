<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum DefensiveAbilityTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case PhysicalDefense = 'physical_defense';
    case MagicalDefense = 'magical_defense';
    case UnusualDefense = 'unusual_defense';
}
