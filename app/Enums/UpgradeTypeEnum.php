<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum UpgradeTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Crew = 'crew';
    case Character = 'character';
}
