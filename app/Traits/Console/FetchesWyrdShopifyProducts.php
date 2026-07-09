<?php

namespace App\Traits\Console;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared paginated-fetch + backoff logic for the Wyrd Shopify store's public
 * `products.json` endpoints. ImportWyrdPackages and ImportWyrdTosPackages
 * both hit the same origin, so they share one rate-limit cooldown: retrying
 * from either command while the origin is actively throttling this IP would
 * just keep re-tripping (or extending, if Cloudflare's mitigation window is
 * sliding) whatever block is already in effect.
 */
trait FetchesWyrdShopifyProducts
{
    private const RATE_LIMIT_COOLDOWN_KEY = 'wyrd-import:rate-limited-until';

    private const RATE_LIMIT_COOLDOWN_MINUTES = 20;

    /**
     * Resolves the raw product list either from a previously-dumped JSON
     * file (`--from-file`, when the machine running the import is itself
     * blocked by Wyrd's origin) or by fetching live. `--dump-to` writes
     * whichever result was used back out to a file — run this on an
     * unblocked machine, then feed the file to `--from-file` wherever the
     * import actually needs to run.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveWyrdProducts(string $endpoint): array
    {
        $fromFile = $this->option('from-file');
        $products = $fromFile
            ? $this->loadWyrdProductsFromFile((string) $fromFile)
            : $this->fetchAllWyrdProducts($endpoint);

        $dumpTo = $this->option('dump-to');
        if ($dumpTo && file_put_contents($dumpTo, json_encode($products)) !== false) {
            $this->info(sprintf('Wrote %d products to %s', count($products), $dumpTo));
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadWyrdProductsFromFile(string $path): array
    {
        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            $this->error("Could not parse {$path} as a JSON array of products.");

            return [];
        }

        $this->info(sprintf('Loaded %d products from %s', count($decoded), $path));

        return $decoded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllWyrdProducts(string $endpoint): array
    {
        $cooldownUntil = Cache::get(self::RATE_LIMIT_COOLDOWN_KEY);
        if ($cooldownUntil && now()->lt($cooldownUntil)) {
            $this->warn(sprintf(
                'Skipping fetch — a recent run was rate-limited by the Wyrd store; cooling down until %s. Retrying now would likely extend the block.',
                $cooldownUntil,
            ));

            return [];
        }

        $allProducts = [];
        $page = 1;

        while (true) {
            $response = $this->fetchWyrdPage($endpoint, $page);

            if ($response === null) {
                break;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                break;
            }

            $allProducts = array_merge($allProducts, $products);
            $page++;

            // Shopify's storefront API rate-limits aggressively — a pause
            // between pages avoids tripping it in the first place.
            usleep(750_000);
        }

        return $allProducts;
    }

    /**
     * Fetches one page, retrying with backoff on 429s (honoring Retry-After
     * when Shopify sends one). If every attempt still 429s, the origin is
     * actively blocking this IP rather than momentarily throttling it —
     * hammering it every ~60s (the old behavior) risks re-triggering or
     * extending a Cloudflare mitigation window, so a final failure here sets
     * a cooldown that every Wyrd-import command respects instead of retrying
     * immediately.
     */
    private function fetchWyrdPage(string $endpoint, int $page, int $maxAttempts = 3): ?Response
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; BiggerHatImporter/1.0; +https://biggerhat.io)',
            ])->get($endpoint, [
                'limit' => 100,
                'page' => $page,
            ]);

            if ($response->successful()) {
                return $response;
            }

            if ($response->status() !== 429) {
                $this->error("Failed to fetch page {$page}: ".$response->status());

                return null;
            }

            if ($attempt < $maxAttempts) {
                $wait = $response->hasHeader('Retry-After') ? (int) $response->header('Retry-After') : $attempt * 30;
                $this->warn("  Rate limited fetching page {$page} (attempt {$attempt}/{$maxAttempts}) — waiting {$wait}s...");
                sleep($wait);

                continue;
            }

            Cache::put(self::RATE_LIMIT_COOLDOWN_KEY, now()->addMinutes(self::RATE_LIMIT_COOLDOWN_MINUTES));
            $this->error(sprintf(
                'Still rate-limited fetching page %d after %d attempts — backing off %d minutes before any Wyrd import command will retry.',
                $page,
                $maxAttempts,
                self::RATE_LIMIT_COOLDOWN_MINUTES,
            ));

            return null;
        }

        return null;
    }
}
