<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CharacterStationEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Master = 'master';
    case Henchman = 'henchman';
    case Minion = 'minion';
    case Peon = 'peon';

    public static function sortOrder(): array
    {
        return [
            0 => self::Master->value,
            1 => self::Henchman->value,
            2 => null,
            3 => self::Minion->value,
            4 => self::Peon->value,
        ];
    }
}
