<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum ResistanceTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Defense = 'Df';
    case Willpower = 'Wp';
    case DefenseWillpower = 'Df/Wp';
    case Move = 'Mv';
    case Size = 'Sz';
    case X = 'X';
    case Star = '*';
}
