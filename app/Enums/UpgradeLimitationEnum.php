<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum UpgradeLimitationEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case RestrictedNonMaster = 'restricted_non_master';

    public function label(): string
    {
        return match ($this) {
            self::RestrictedNonMaster => 'Restricted (Non-Master)',
            default => Str::headline($this->name),
        };
    }
}
