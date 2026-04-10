<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum PodSourceEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case ForgeFire = 'forgefire';
    case WargameVault = 'wargame_vault';

    public function label(): string
    {
        return match ($this) {
            self::ForgeFire => 'ForgeFire',
            self::WargameVault => 'Wargame Vault',
        };
    }
}
