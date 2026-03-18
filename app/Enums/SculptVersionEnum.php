<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumSelectOptions;
use Illuminate\Support\Str;

enum SculptVersionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumSelectOptions;

    case FirstEdition = 'first_edition';
    case SecondEdition = 'second_edition';
    case ThirdEdition = 'third_edition';
    case FourthEdition = 'fourth_edition';
    case AlternateModel = 'alternate_model';
    case SpecialEdition = 'special_edition';
    case Nightmare = 'nightmare';
    case RottenHarvest = 'rotten_harvest';

    public function label(): string
    {
        return match ($this) {
            self::FirstEdition => '1st Edition',
            self::SecondEdition => '2nd Edition',
            self::ThirdEdition => '3rd Edition',
            self::FourthEdition => '4th Edition',
            default => Str::headline($this->name),
        };
    }

    /**
     * @return SculptVersionEnum[]
     */
    public static function standardEditions(): array
    {
        return [
            self::FirstEdition,
            self::SecondEdition,
            self::ThirdEdition,
            self::FourthEdition,
        ];
    }

    /**
     * @return SculptVersionEnum[]
     */
    public static function promotionalEditions(): array
    {
        return [
            self::SpecialEdition,
            self::AlternateModel,
            self::Nightmare,
            self::RottenHarvest,
        ];
    }

    public function promotionalTitle(): string
    {
        return match ($this) {
            self::AlternateModel => 'Alt',
            self::SpecialEdition => 'Special Edition',
            self::Nightmare => 'Nightmare',
            self::RottenHarvest => 'Rotten Harvest',
            default => '',
        };
    }
}
