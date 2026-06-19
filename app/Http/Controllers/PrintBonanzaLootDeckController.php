<?php

namespace App\Http\Controllers;

use App\Models\LootCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Printer-friendly PDF of the Bonanza Brawl Loot Deck. Tiles the generated
 * card images (captured in light mode — white background, dark text, coloured
 * suit borders, mirrored Side B) into a cut-grid so players can print and cut
 * a usable deck.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(): \Illuminate\Http\Response
    {
        $images = LootCard::query()
            ->whereNotNull('image')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (LootCard $card) => [
                'image' => base64_encode(Storage::disk('public')->get($card->image)),
                'name' => $card->name,
            ])
            ->all();

        $pdf = Pdf::loadView('PDF.BonanzaDeck', ['images' => $images])
            ->setPaper('letter', 'portrait');

        return $pdf->stream('bonanza-loot-deck.pdf');
    }
}
