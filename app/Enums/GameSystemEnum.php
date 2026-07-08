<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;

enum GameSystemEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case Malifaux = 'malifaux';
    case Tos = 'tos';
    // Some Wyrd products (e.g. crossover terrain/accessory packages) are sold
    // for both game lines — this lets one Package row appear in both browse
    // lists instead of forcing an arbitrary primary system or duplicating
    // the row.
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Malifaux => 'Malifaux',
            self::Tos => 'The Other Side',
            self::Both => 'Both (Malifaux & TOS)',
        };
    }
}
