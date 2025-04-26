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
                'color' => $case->backgroundColor(),
            ];
        }

        return $details;
    }

    public function backgroundColor(): string
    {
        return match ($this) {
            self::Arcanists => 'bg-arcanists',
            self::Bayou => 'bg-bayou',
            self::ExplorersSociety => 'bg-explorerssociety',
            self::Guild => 'bg-guild',
            self::Neverborn => 'bg-neverborn',
            self::Outcasts => 'bg-outcasts',
            self::Resurrectionists => 'bg-resurrectionists',
            self::TenThunders => 'bg-tenthunders',
            default => '',
        };
    }
}
