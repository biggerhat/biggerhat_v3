<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum ActionRangeTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Gun = 'gun';
    case Magic = 'magic';
    case Melee = 'melee';
    case Pulse = 'pulse';
    case Aura = 'aura';
}
