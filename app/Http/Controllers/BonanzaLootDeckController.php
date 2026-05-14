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
                // Column lists mirror what ActionCard / AbilityCard / TriggerCard
                // need so the public display can render icons (suits, range type,
                // stones, etc.) instead of bare names.
                'sideAActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
                'sideBActions:id,name,slug,type,is_signature,stone_cost,range,range_type,stat,stat_suits,stat_modifier,resisted_by,target_number,target_suits,damage,description',
                // Each action carries its own triggers via the action_trigger
                // pivot — surface them here so the compact action display can
                // render them under the action without a second request.
                'sideAActions.triggers:id,name,slug,suits,stone_cost,description',
                'sideBActions.triggers:id,name,slug,suits,stone_cost,description',
                'sideAAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
                'sideBAbilities:id,name,slug,suits,defensive_ability_type,costs_stone,description',
                'sideATriggers:id,name,slug,suits,stone_cost,description',
                'sideBTriggers:id,name,slug,suits,stone_cost,description',
            ])
            ->get();

        return inertia('Tools/BonanzaLootDeck', [
            'cards' => $cards,
        ]);
    }
}
