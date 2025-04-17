<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum SuitEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Crow = 'crow';
    case Mask = 'mask';
    case Ram = 'ram';
    case Tome = 'tome';
    case Soulstone = 'soulstone';
}
