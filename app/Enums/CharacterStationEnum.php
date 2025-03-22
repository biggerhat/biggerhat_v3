<?php

namespace App\Enums;

use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CharacterStationEnum: string
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Minion = 'minion';
    case Enforcer = 'enforcer';
    case Henchman = 'henchman';
    case Master = 'master';
}
