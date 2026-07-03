<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum CrewUpgradeRestrictionDescriptorTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Action = 'action';
    case Ability = 'ability';
    case Trigger = 'trigger';

    public function label(): string
    {
        return match ($this) {
            self::Action => 'actions',
            self::Ability => 'abilities',
            self::Trigger => 'triggers',
        };
    }
}
