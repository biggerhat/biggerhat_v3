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
 *
 * The cache filename embeds a hash of the Blade template, so a template/glyph
 * change (e.g. a new icon mapping) busts the cache on the next request even
 * when no card data has changed — otherwise a deployed render fix would keep
 * serving the old PDF until someone edited a card.
 */
class BonanzaDeckPdfGenerator
{
    private const VIEW = 'PDF.BonanzaDeck';

    private const VIEW_FILE = 'views/PDF/BonanzaDeck.blade.php';

    /** Cached PDF location on the `public` disk, versioned by template hash. */
    public function cachePath(): string
    {
        return "bonanza/loot-deck-{$this->renderHash()}.pdf";
    }

    /** Short hash of the Blade template — changes whenever the render changes. */
    private function renderHash(): string
    {
        $file = resource_path(self::VIEW_FILE);

        return is_file($file) ? substr(md5_file($file), 0, 10) : 'base';
    }

    /**
     * Build the PDF and store it at cachePath(). Returns the storage path.
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

        $html = View::make(self::VIEW, ['cards' => $cards])->render();

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

        $path = $this->cachePath();
        Storage::disk('public')->put($path, $pdf);
        $this->pruneStaleCaches($path);

        return $path;
    }

    /** Whether a cached PDF for the current template currently exists. */
    public function exists(): bool
    {
        return Storage::disk('public')->exists($this->cachePath());
    }

    /**
     * Stale when there is no cache (incl. after a template change, which moves
     * the cache path), or a card has been edited since it was built. Lets the
     * print route self-heal even if the queue worker that normally pre-warms
     * the cache isn't running.
     */
    public function isStale(): bool
    {
        if (! $this->exists()) {
            return true;
        }

        $latest = LootCard::max('updated_at');
        if ($latest === null) {
            return false;
        }

        return strtotime((string) $latest) > (int) $this->generatedAt();
    }

    /** Public URL of the cached PDF (null if not yet generated). */
    public function url(): ?string
    {
        return $this->exists() ? Storage::disk('public')->url($this->cachePath()) : null;
    }

    /** Unix mtime of the cached PDF, or null. */
    public function generatedAt(): ?int
    {
        return $this->exists() ? Storage::disk('public')->lastModified($this->cachePath()) : null;
    }

    /** Delete superseded cache files (old template hashes) to avoid orphans. */
    private function pruneStaleCaches(string $keep): void
    {
        foreach (Storage::disk('public')->files('bonanza') as $file) {
            if ($file !== $keep && str_starts_with(basename($file), 'loot-deck-') && str_ends_with($file, '.pdf')) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
