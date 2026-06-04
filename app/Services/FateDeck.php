<?php

namespace App\Services;

use App\Enums\SuitEnum;

/**
 * Fate-deck values + suits, centralized. The Aftermath wizard draws random
 * cards in Phase 1 and elsewhere; previously these constants were
 * duplicated in `CampaignAftermathController`. SuitEnum already owns the
 * four suit identifiers, so we just narrow it to the fate-deck subset
 * (Soulstone is a suit pool symbol, not a fate-deck card suit).
 */
class FateDeck
{
    /** @var list<int> */
    public const VALUES = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];

    /**
     * The four fate-deck suits as bare strings. Mirrors SuitEnum minus
     * Soulstone, which never appears on a fate card.
     *
     * @return list<string>
     */
    public static function suits(): array
    {
        return [
            SuitEnum::Ram->value,
            SuitEnum::Crow->value,
            SuitEnum::Mask->value,
            SuitEnum::Tome->value,
        ];
    }

    /**
     * Draw a single card. Returns the same shape the aftermath wizard
     * has used since Phase 1: `{ value, suit, is_joker }`. Jokers aren't
     * yet modeled here — the deck draws are uniform across 1-13.
     *
     * @return array{value: int, suit: string, is_joker: bool}
     */
    public static function draw(): array
    {
        $suits = self::suits();

        return [
            'value' => self::VALUES[array_rand(self::VALUES)],
            'suit' => $suits[array_rand($suits)],
            'is_joker' => false,
        ];
    }
}
