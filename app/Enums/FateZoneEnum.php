<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/** Where a Fate card is (CRG p.6-9): the deck, a control hand, the conflict, the discard pile, or removed from the game. */
enum FateZoneEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Deck = 'deck';
    case Hand = 'hand';
    case Conflict = 'conflict';
    case Discard = 'discard';
    case Removed = 'removed';

    /** Zones everyone at the table may see the cards of. */
    public function isPublic(): bool
    {
        return in_array($this, [self::Conflict, self::Discard, self::Removed], true);
    }
}
