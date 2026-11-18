<?php

namespace App\Support\Fate;

/**
 * The 54-card Fate deck (CRG p.6): four suits of thirteen (Rams, Masks, Tomes, Crows) numbered 1-13, a Red Joker (value 14, the suit
 * of its owner's choosing) and a Black Joker (value 0, no suit). A card is its number 0-53: the suits in that order, then the jokers.
 * Original rendering only; no Wyrd card art.
 */
final class FateCards
{
    public const COUNT = 54;

    public const RED_JOKER = 52;

    public const BLACK_JOKER = 53;

    /** The most cards a control hand holds (CRG p.7). */
    public const HAND_MAX = 7;

    /** A flip reveals at most four cards in all (CRG p.8). */
    public const FLIP_MAX = 4;

    public const SUITS = ['rams', 'masks', 'tomes', 'crows'];

    /** @return list<int> */
    public static function all(): array
    {
        return range(0, self::COUNT - 1);
    }

    public static function valid(int $card): bool
    {
        return $card >= 0 && $card < self::COUNT;
    }

    public static function value(int $card): int
    {
        return match ($card) {
            self::RED_JOKER => 14,
            self::BLACK_JOKER => 0,
            default => $card % 13 + 1,
        };
    }

    /** The suit a card has, or null for a joker (a Red Joker takes the suit its owner picks). */
    public static function suit(int $card): ?string
    {
        return $card < self::RED_JOKER ? self::SUITS[intdiv($card, 13)] : null;
    }

    public static function isJoker(int $card): bool
    {
        return $card >= self::RED_JOKER;
    }

    public static function label(int $card): string
    {
        return match ($card) {
            self::RED_JOKER => 'Red Joker',
            self::BLACK_JOKER => 'Black Joker',
            default => self::value($card).' of '.ucfirst((string) self::suit($card)),
        };
    }
}
