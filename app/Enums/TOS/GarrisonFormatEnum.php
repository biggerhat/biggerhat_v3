<?php

namespace App\Enums\TOS;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Garrison formats for Fields-of-Glory tournaments. Each case carries the
 * validation profile a Garrison built for that tournament size must meet:
 * commander cap, scrip budget for Units+Assets combined, Stratagem count,
 * and Envoy slot count. Helpers below are the source of truth — controllers
 * and the Vue builder both read from here so the rules stay in one place.
 */
enum GarrisonFormatEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case OneCommander = 'one_commander';
    case OneCommanderPlus10 = 'one_commander_plus_10';
    case TwoCommanders = 'two_commanders';
    case TheaterOfWar = 'theater_of_war';
    case NoMansLand = 'no_mans_land';

    public function label(): string
    {
        return match ($this) {
            self::OneCommander => 'One Commander',
            self::OneCommanderPlus10 => 'One Commander +10 Scrip',
            self::TwoCommanders => 'Two Commanders',
            self::TheaterOfWar => 'Theater of War',
            self::NoMansLand => "No Man's Land",
        };
    }

    /**
     * The Same-Name cap is also driven by this — a Garrison cannot include
     * more units of the same name than the maximum number of Commanders the
     * format allows.
     */
    public function maxCommanders(): int
    {
        return match ($this) {
            self::OneCommander, self::OneCommanderPlus10, self::NoMansLand => 2,
            self::TwoCommanders, self::TheaterOfWar => 3,
        };
    }

    /** Combined Scrip ceiling for non-Commander Units + Assets. */
    public function scripBudget(): int
    {
        return match ($this) {
            self::OneCommander, self::NoMansLand => 40,
            self::OneCommanderPlus10 => 50,
            self::TwoCommanders, self::TheaterOfWar => 75,
        };
    }

    public function stratagemCount(): int
    {
        return match ($this) {
            self::OneCommander => 6,
            self::OneCommanderPlus10, self::NoMansLand => 8,
            self::TwoCommanders, self::TheaterOfWar => 12,
        };
    }

    public function envoyCount(): int
    {
        return match ($this) {
            self::OneCommander, self::NoMansLand => 0,
            self::OneCommanderPlus10, self::TwoCommanders, self::TheaterOfWar => 1,
        };
    }

    // ── Company-play semantics ──────────────────────────────────────────
    // The methods above (maxCommanders/scripBudget/stratagemCount) describe a
    // tournament *Garrison pool*. The two below describe how a single Company
    // actually plays the format: the rulebook's "game size" is the number of
    // Commanders fielded, and the budget is the sum of those Commanders' Scrip
    // (Fields of Glory packet). They are intentionally distinct from the pool
    // numbers above.

    /**
     * Maximum Commanders a Company fields under this format. One-Commander
     * games (incl. +10) and No Man's Land field one; Two Commanders and
     * Theater of War (whose later rounds are 2-Commander) field up to two.
     */
    public function commandersFielded(): int
    {
        return match ($this) {
            self::OneCommander, self::OneCommanderPlus10, self::NoMansLand => 1,
            self::TwoCommanders, self::TheaterOfWar => 2,
        };
    }

    /** Flat Scrip added on top of the fielded Commanders' Scrip ratings. */
    public function scripBonus(): int
    {
        return match ($this) {
            self::OneCommanderPlus10 => 10,
            default => 0,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OneCommander => 'Two Commanders, 40 Scrip of Units and Assets, any 6 Stratagems.',
            self::OneCommanderPlus10 => 'One-Commander game size with +10 Scrip. Two Commanders, 50 Scrip, any 8 Stratagems, one Envoy.',
            self::TwoCommanders => 'Three Commanders, 75 Scrip, any 12 Stratagems, one Envoy.',
            self::TheaterOfWar => 'First two Rounds 1-Commander; remaining Rounds 2-Commander. Three Commanders, 75 Scrip, any 12 Stratagems, one Envoy.',
            self::NoMansLand => 'Team tournament (Confederation rules). Two Commanders, 40 Scrip, any 8 Stratagems.',
        };
    }
}
