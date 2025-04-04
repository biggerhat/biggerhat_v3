<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CharacterStationEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Peon = 'peon';
    case Minion = 'minion';
    case Enforcer = 'enforcer';
    case Henchman = 'henchman';
    case Master = 'master';
}
