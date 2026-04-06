<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;

enum CrewUpgradeModeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case SelectOne = 'select_one';
    case Swappable = 'swappable';

    public function label(): string
    {
        return match ($this) {
            self::SelectOne => 'Select One (Locked)',
            self::Swappable => 'Swappable (During Gameplay)',
        };
    }
}
