<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Public reference for the Bonanza Brawl Loot Deck. The 54 cards plus their
 * canonical effects (filled in by an admin from the Wyrd doc). Per-game
 * loot-deck state — drawing, claiming, dropping markers — lives on the
 * Game Tracker; this is purely a lookup so players can see what each card
 * does mid-game without fishing through the rulebook.
 */
class BonanzaLootDeckController extends Controller
{
    public function __invoke(): Response|ResponseFactory
    {
        $cards = LootCard::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with([
                // Side-specific relations are pre-filtered by `wherePivot` on
                // the model, so each load only ships what's relevant per side.
                'sideAActions:id,name,slug,description',
                'sideBActions:id,name,slug,description',
                'sideAAbilities:id,name,slug,description',
                'sideBAbilities:id,name,slug,description',
                'sideATriggers:id,name,slug,description',
                'sideBTriggers:id,name,slug,description',
            ])
            ->get();

        return inertia('Tools/BonanzaLootDeck', [
            'cards' => $cards,
        ]);
    }
}
