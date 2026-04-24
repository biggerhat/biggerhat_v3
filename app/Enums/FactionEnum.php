<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Models\Character;
use App\Models\Keyword;
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

    /**
     * Memoize per-process — buildDetails is called via the Inertia shared
     * data on every request and is a pure function of static enum data.
     * Enums can't carry class-level properties, so we memoize via a static
     * local instead.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function buildDetails(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $details = [];
        foreach (self::cases() as $case) {
            $details[$case->value] = [
                'slug' => $case->value,
                'name' => $case->label(),
                'color' => $case->color(),
                'logo' => $case->logo(),
            ];
        }

        return $cache = $details;
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

    public function getCharacterStats(): array
    {
        $characters = Character::standard()->where('faction', $this->value)->get();

        return [
            'characters' => $characters->count(),
            'miniatures' => $characters->sum('count'),
            'keywords' => Keyword::standard()->whereHas('characters', function ($query) {
                $query->where('faction', $this->value);
            })->count(),
        ];
    }
}
