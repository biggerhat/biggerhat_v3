<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CharacterSortOptionsEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Name = 'name';
    case Cost = 'cost';
    case Health = 'health';
    case Speed = 'speed';
    case Defense = 'defense';
    case Willpower = 'willpower';
    case Size = 'size';
    case BaseSize = 'base_size';
}
