<?php

namespace Database\Seeders;

use App\Models\LootCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LootCardSeeder extends Seeder
{
    /**
     * Seed the 54-card Bonanza Brawl Loot Deck. Structure only — names are
     * drawn from the standard fate-deck layout (e.g. "Five of Crows"); the
     * `effect_a` / `effect_b` columns are intentionally left null so a TO /
     * super-admin can fill them in via the admin UI from the canonical Wyrd
     * doc (wyrdgames.net/bonanza-loot-deck). Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $suits = [
            // Order matches the rulebook's flank-deployment-zone mapping
            // (Crows / Masks / Rams / Tomes), so seed sort_order follows it.
            'crow' => 'Crow',
            'mask' => 'Mask',
            'ram' => 'Ram',
            'tome' => 'Tome',
        ];

        $valueLabels = [
            1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7',
            8 => '8', 9 => '9', 10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K',
        ];

        $valueWords = [
            1 => 'Ace', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
            7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Jack', 12 => 'Queen', 13 => 'King',
        ];

        $sort = 0;
        foreach ($suits as $suit => $suitName) {
            foreach ($valueLabels as $value => $label) {
                $name = "{$valueWords[$value]} of {$suitName}s";
                LootCard::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'suit' => $suit,
                        'value' => $value,
                        'value_label' => $label,
                        'name' => $name,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }

        // Two jokers — physically distinct (red / black) so we model them as
        // two rows. Suit = 'joker' since the cards have no suit; value is
        // null because rule-text references "either Joker", not a numeric.
        foreach (['Red Joker', 'Black Joker'] as $jokerLabel) {
            LootCard::updateOrCreate(
                ['slug' => Str::slug($jokerLabel)],
                [
                    'suit' => 'joker',
                    'value' => null,
                    'value_label' => $jokerLabel,
                    'name' => $jokerLabel,
                    'sort_order' => $sort++,
                ],
            );
        }
    }
}
