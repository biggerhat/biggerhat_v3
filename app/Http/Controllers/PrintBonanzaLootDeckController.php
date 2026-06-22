<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Printer-friendly PDF of the Bonanza Brawl Loot Deck. Renders directly from
 * the card DATA — no stored images involved. Always current, zero maintenance,
 * no regeneration step. DomPDF handles the HTML→PDF; the Blade builds a clean
 * text-based card at tarot size with suit-coloured accents.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $cards = LootCard::query()
            ->with([
                'sideAActions.triggers', 'sideBActions.triggers',
                'sideAAbilities', 'sideBAbilities',
                'sideATriggers', 'sideBTriggers',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $pdf = Pdf::loadView('PDF.BonanzaDeck', ['cards' => $cards])
            ->setPaper('letter', 'portrait');

        return $pdf->stream('bonanza-loot-deck.pdf');
    }
}
