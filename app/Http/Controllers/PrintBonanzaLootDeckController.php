<?php

namespace App\Http\Controllers;

use App\Services\BonanzaDeckPdfGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the printer-friendly Bonanza Brawl Loot Deck PDF. The PDF is rendered
 * server-side by headless Chrome (vector-crisp, tarot sized, 4 per Letter page)
 * and cached — see BonanzaDeckPdfGenerator. The cache is refreshed in the
 * background whenever a loot card changes; here we just stream it, generating
 * once inline if it has never been built.
 */
class PrintBonanzaLootDeckController extends Controller
{
    public function __invoke(BonanzaDeckPdfGenerator $generator): \Illuminate\Http\Response
    {
        if ($generator->isStale()) {
            @set_time_limit(180);
            $generator->generate();
        }

        $pdf = Storage::disk('public')->get($generator->cachePath());

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bonanza-loot-deck.pdf"',
        ]);
    }
}
