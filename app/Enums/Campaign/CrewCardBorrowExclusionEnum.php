<?php

namespace App\Enums\Campaign;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Tier-4 Crew Card Advancement (pg 32, 54): "Choose an effect granted by any
 * crew card associated with a master that has a keyword this crew chose...
 * Effects that reference a power bar or cause the crew card to be swapped
 * with a different crew card may not be chosen." Set on a specific action or
 * ability row (`campaign_crew_card_actions`/`campaign_crew_card_abilities`
 * pivot) to exclude it from that borrowing pool — the effect still exists
 * normally as part of its own crew card's starter set.
 */
enum CrewCardBorrowExclusionEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case PowerBar = 'power_bar';
    case CardSwap = 'card_swap';
    case Other = 'other';
}
