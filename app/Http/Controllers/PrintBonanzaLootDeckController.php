<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Public reference for the Bonanza Brawl Loot Deck. The 54 cards plus their
 * canonical effects (filled in by an admin from the Wyrd doc). Per-game
 * loot-deck state — drawing, claiming, dropping markers — lives on the
 * Game Tracker; this is purely a lookup so players can see what each card
 * does mid-game without fishing through the rulebook.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        $lootCards = LootCard::all();
        $data = [];
        $data['images'] = $lootCards->toArray();

        $pdf = Pdf::loadView('PDF.BonanzaDeck', $data);

        $fileName = \Str::uuid();

        return $pdf->stream("{$fileName}.pdf");
    }
}
