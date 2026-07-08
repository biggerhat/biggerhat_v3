<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;

enum GameSystemEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case Malifaux = 'malifaux';
    case Tos = 'tos';

    public function label(): string
    {
        return match ($this) {
            self::Malifaux => 'Malifaux',
            self::Tos => 'The Other Side',
        };
    }
}
