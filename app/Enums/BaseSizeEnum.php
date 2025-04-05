<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum BaseSizeEnum: int implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case ThirtyMM = 30;
    case FortyMM = 40;
    case FiftyMM = 50;

    public function label(): string
    {
        return match ($this) {
            self::ThirtyMM => '30mm',
            self::FortyMM => '40mm',
            self::FiftyMM => '50mm',
            default => Str::headline($this->name),
        };
    }
}
