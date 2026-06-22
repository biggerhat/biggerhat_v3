<?php

namespace App\Services;

use App\Models\LootCard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders the Bonanza Brawl Loot Deck to a print-ready PDF via headless Chrome
 * (Browsershot) and caches it on the public disk. Vector-crisp, tarot sized,
 * 4 cards per Letter page — looks exactly like the on-screen card.
 */
class BonanzaDeckPdfGenerator
{
    /** Cached PDF location on the `public` disk. */
    public const PATH = 'bonanza/loot-deck.pdf';

    /**
     * Build the PDF and store it at self::PATH. Returns the storage path.
     */
    public function generate(): string
    {
        $cards = LootCard::query()
            ->with([
                'sideAActions.triggers', 'sideBActions.triggers',
                'sideAAbilities', 'sideBAbilities',
                'sideATriggers', 'sideBTriggers',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $html = View::make('PDF.BonanzaDeck', ['cards' => $cards])->render();

        $browsershot = Browsershot::html($html)
            ->noSandbox()
            ->format('Letter')
            ->showBackground()
            ->margins(0, 0, 0, 0)
            ->timeout(120);

        if ($node = config('services.browsershot.node_binary')) {
            $browsershot->setNodeBinary($node);
        }
        if ($npm = config('services.browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npm);
        }
        if ($chrome = config('services.browsershot.chrome_path')) {
            $browsershot->setChromePath($chrome);
        }

        $pdf = $browsershot->pdf();

        Storage::disk('public')->put(self::PATH, $pdf);

        return self::PATH;
    }

    /** Whether a cached PDF currently exists. */
    public function exists(): bool
    {
        return Storage::disk('public')->exists(self::PATH);
    }

    /** Public URL of the cached PDF (null if not yet generated). */
    public function url(): ?string
    {
        return $this->exists() ? Storage::disk('public')->url(self::PATH) : null;
    }

    /** Unix mtime of the cached PDF, or null. */
    public function generatedAt(): ?int
    {
        return $this->exists() ? Storage::disk('public')->lastModified(self::PATH) : null;
    }
}
