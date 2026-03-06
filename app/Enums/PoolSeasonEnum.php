<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum PoolSeasonEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case GainingGrounds0 = 'core';

    public function label(): string
    {
        return match ($this) {
            self::GainingGrounds0 => 'Gaining Grounds 0',
            default => Str::headline($this->name),
        };
    }

    public static function defaultSeason(): self
    {
        return self::GainingGrounds0;
    }
}
