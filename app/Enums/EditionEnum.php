<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum EditionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case FirstEdition = 'first_edition';
    case SecondEdition = 'second_edition';
    case ThirdEdition = 'third_edition';
    case FourthEdition = 'fourth_edition';
    case SpecialEdition = 'special_edition';

    public function label(): string
    {
        return match ($this) {
            self::FirstEdition => '1st Edition',
            self::SecondEdition => '2nd Edition',
            self::ThirdEdition => '3rd Edition',
            self::FourthEdition => '4th Edition',
            self::SpecialEdition => 'Special Edition',
            default => Str::headline($this->name),
        };
    }
}
