<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * How a game is going to be played, as chosen when it is created. Only {@see self::Tabletop} changes what the app does (it
 * opens on a chosen table and, later, runs its deployment there); the others are a label for lists and stats. A game made
 * before this existed has no mode (null), and is never guessed at.
 */
enum GamePlayModeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case InPerson = 'in_person';
    case Tabletop = 'tabletop';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'In person',
            self::Tabletop => 'On the tabletop app',
            self::Online => 'Online (not the tabletop app)',
            self::Other => 'Other',
        };
    }

    /** The short form for a badge in a list. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::InPerson => 'In person',
            self::Tabletop => 'Tabletop',
            self::Online => 'Online',
            self::Other => 'Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::InPerson => 'Models on a real table; the tracker keeps health, tokens, schemes and score.',
            self::Tabletop => 'Play on the app\'s virtual table, with a saved table and its own setup.',
            self::Online => 'Another virtual tabletop or a video call; the tracker keeps the rest.',
            self::Other => 'Anything else.',
        };
    }

    public function usesTabletop(): bool
    {
        return $this === self::Tabletop;
    }
}
