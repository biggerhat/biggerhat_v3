<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum PackageCategoryEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case CoreBox = 'core_box';
    case Expansion = 'expansion';
    case Iconic = 'iconic';
    case Title = 'title';
    case Nightmare = 'nightmare';
    case Alternate = 'alternate';
    case Accessories = 'accessories';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CoreBox => 'Core Box',
            default => Str::headline($this->name),
        };
    }
}
