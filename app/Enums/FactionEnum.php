<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum FactionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Arcanists = 'arcanists';
    case Bayou = 'bayou';
    case Guild = 'guild';
    case ExplorersSociety = 'explorers_society';
    case Neverborn = 'neverborn';
    case Outcasts = 'outcasts';
    case Resurrectionists = 'resurrectionists';
    case TenThunders = 'ten_thunders';

    public static function buildDetails(): array
    {
        $details = [];
        foreach (self::cases() as $case) {
            $details[$case->value] = [
                'name' => $case->label(),
                'color' => $case->color(),
            ];
        }

        return $details;
    }

    public function color(): string
    {
        return match ($this) {
            self::Arcanists => 'arcanists',
            self::Bayou => 'bayou',
            self::ExplorersSociety => 'explorerssociety',
            self::Guild => 'guild',
            self::Neverborn => 'neverborn',
            self::Outcasts => 'outcasts',
            self::Resurrectionists => 'resurrectionists',
            self::TenThunders => 'tenthunders',
            default => '',
        };
    }

    public function logo(): string
    {
        return match ($this) {
            self::Arcanists => '/images/Factions/M4E-Arcanists.png',
            self::Bayou => '/images/Factions/M4E-Bayou.png',
            self::ExplorersSociety => '/images/Factions/M4E-Explorers.png',
            self::Guild => '/images/Factions/M4E-Guild.png',
            self::Neverborn => '/images/Factions/M4E-Neverborn.png',
            self::Outcasts => '/images/Factions/M4E-Outcasts.png',
            self::Resurrectionists => '/images/Factions/M4E-Resurrectionists.png',
            self::TenThunders => '/images/Factions/M4E-Ten-Thunders.png',
        };
    }
}
