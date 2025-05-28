<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CharacterStationEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    const NON_STATION_SORT_ORDER = 2;

    case Master = 'master';
    case Henchman = 'henchman';
    case Minion = 'minion';
    case Peon = 'peon';

    public function sortOrder(): int
    {
        return match ($this) {
            self::Master => 0,
            self::Henchman => 1,
            self::Minion => 3,
            self::Peon => 4,
            default => self::NON_STATION_SORT_ORDER,
        };
    }
}
