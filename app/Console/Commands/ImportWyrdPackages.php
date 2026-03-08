<?php

namespace App\Console\Commands;

use App\Enums\FactionEnum;
use App\Enums\PackageCategoryEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Package;
use App\Models\PackageStoreLink;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWyrdPackages extends Command
{
    protected $signature = 'app:import-wyrd-packages
        {--dry-run : Show what would be imported without saving}
        {--skip-images : Skip downloading product images}
        {--force : Update existing packages instead of skipping}';

    protected $description = 'Import packages from the Wyrd Miniatures Shopify store';

    private const BASE_URL = 'https://giveusyourmoneypleasethankyou-wyrd.com';

    private const MALIFAUX_TAGS = ['M3E', 'M3e', 'M4E', 'M4e'];

    private const FACTION_TAG_MAP = [
        'Arcanist' => 'arcanists',
        'Arcanists' => 'arcanists',
        'Bayou' => 'bayou',
        'Explorer' => 'explorers_society',
        'Explorers' => 'explorers_society',
        'Guild' => 'guild',
        'Neverborn' => 'neverborn',
        'Outcast' => 'outcasts',
        'Outcasts' => 'outcasts',
        'Resurrectionists' => 'resurrectionists',
        'Ten Thunders' => 'ten_thunders',
    ];

    /** @var Collection<int, Character> */
    private Collection $allCharacters;

    public function handle(): int
    {
        $this->info('Fetching products from Wyrd store...');

        $this->allCharacters = Character::all();

        $products = $this->fetchAllProducts();
        $malifauxProducts = $this->filterMalifauxProducts($products);

        $this->info(sprintf('Found %d total products, %d Malifaux packages.', count($products), count($malifauxProducts)));

        if (count($malifauxProducts) === 0) {
            $this->warn('No Malifaux products found.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $totalLinked = 0;

        foreach ($malifauxProducts as $product) {
            [$result, $linked] = $this->processProduct($product);
            $totalLinked += $linked;
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $skipped++,
            };
        }

        $this->newLine();
        $this->info(sprintf('Done! Created: %d, Updated: %d, Skipped: %d, Characters linked: %d', $created, $updated, $skipped, $totalLinked));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllProducts(): array
    {
        $allProducts = [];
        $page = 1;

        while (true) {
            $response = Http::get(self::BASE_URL.'/products.json', [
                'limit' => 250,
                'page' => $page,
            ]);

            if (! $response->successful()) {
                $this->error("Failed to fetch page {$page}: ".$response->status());
                break;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                break;
            }

            $allProducts = array_merge($allProducts, $products);
            $page++;
        }

        return $allProducts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function filterMalifauxProducts(array $products): array
    {
        return array_values(array_filter($products, function (array $product) {
            $tags = $product['tags'] ?? [];
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }

            return ! empty(array_intersect($tags, self::MALIFAUX_TAGS));
        }));
    }

    /**
     * @return array{string, int}
     */
    private function processProduct(array $product): array
    {
        $sku = $product['variants'][0]['sku'] ?? null;
        $name = $product['title'] ?? '';
        $slug = $product['handle'] ?? Str::slug($name);

        $existing = Package::where('slug', $slug)
            ->when($sku, fn ($q) => $q->orWhere('sku', $sku))
            ->first();

        if ($existing && ! $this->option('force')) {
            $this->line("  <comment>SKIP</comment> {$name} (already exists)");

            return ['skipped', 0];
        }

        $tags = $product['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $factions = $this->extractFactions($tags);
        $sculptVersion = $this->extractSculptVersion($tags);
        $category = $this->detectCategory($name, $tags);
        $price = $this->extractPrice($product);
        $description = $this->extractDescription($product);
        $contentItems = $this->extractContentItems($product['body_html'] ?? '');
        $characterSyncData = $this->matchCharactersWithQuantity($contentItems);

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  <info>%s</info> %s | SKU: %s | $%s | Factions: %s | %s',
                $existing ? 'UPDATE' : 'CREATE',
                $name,
                $sku ?? 'N/A',
                $price ? number_format($price / 100, 2) : 'N/A',
                implode(', ', $factions) ?: 'none',
                $sculptVersion->label().' | '.$category->label(),
            ));
            foreach ($contentItems as $item) {
                $matchId = collect($characterSyncData)->search(fn ($pivot) => $this->allCharacters->where('id', key([$pivot]))->isNotEmpty());
                $match = null;
                foreach ($characterSyncData as $id => $pivot) {
                    $candidate = $this->allCharacters->firstWhere('id', $id);
                    if ($candidate && $this->namesMatch($candidate->display_name, $item['name'])) {
                        $match = $candidate;
                        break;
                    }
                }
                $qty = $item['quantity'] > 1 ? " (x{$item['quantity']})" : '';
                if ($match) {
                    $this->line("    <info>✓</info> {$item['name']}{$qty} → {$match->display_name}");
                } else {
                    $this->line("    <comment>?</comment> {$item['name']}{$qty} → no match");
                }
            }

            return [$existing ? 'updated' : 'created', count($characterSyncData)];
        }

        $images = $product['images'] ?? [];
        $frontImageUrl = $images[0]['src'] ?? null;
        $backImageUrl = $images[1]['src'] ?? null;

        $frontImage = null;
        $backImage = null;

        if (! $this->option('skip-images')) {
            $frontImage = $frontImageUrl ? $this->downloadImage($frontImageUrl, $slug, 'front') : null;
            $backImage = $backImageUrl ? $this->downloadImage($backImageUrl, $slug, 'back') : null;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'factions' => ! empty($factions) ? $factions : null,
            'description' => $description,
            'msrp' => $price,
            'sku' => $sku,
            'sculpt_version' => $sculptVersion,
            'category' => $category,
            'is_preassembled' => false,
        ];

        if (! $this->option('skip-images')) {
            if ($frontImage) {
                $data['front_image'] = $frontImage;
            }
            if ($backImage) {
                $data['back_image'] = $backImage;
            }
        }

        $linkedCount = count($characterSyncData);

        $storeUrl = self::BASE_URL.'/products/'.$slug;

        if ($existing) {
            $existing->update($data);
            $existing->characters()->sync($characterSyncData);
            $this->syncStoreLink($existing, $storeUrl);
            $this->line("  <info>UPDATED</info> {$name} ({$linkedCount} characters linked)");

            return ['updated', $linkedCount];
        }

        $package = Package::create($data);
        $package->characters()->sync($characterSyncData);
        $this->syncStoreLink($package, $storeUrl);
        $this->line("  <info>CREATED</info> {$name} ({$linkedCount} characters linked)");

        return ['created', $linkedCount];
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, string>
     */
    private function extractFactions(array $tags): array
    {
        $factions = [];
        foreach ($tags as $tag) {
            if (isset(self::FACTION_TAG_MAP[$tag])) {
                $faction = self::FACTION_TAG_MAP[$tag];
                if (FactionEnum::tryFrom($faction) && ! in_array($faction, $factions)) {
                    $factions[] = $faction;
                }
            }
        }

        return $factions;
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function extractSculptVersion(array $tags): SculptVersionEnum
    {
        foreach ($tags as $tag) {
            if (in_array($tag, ['M4E', 'M4e'])) {
                return SculptVersionEnum::FourthEdition;
            }
        }

        return SculptVersionEnum::ThirdEdition;
    }

    private function extractPrice(array $product): ?int
    {
        $price = $product['variants'][0]['price'] ?? null;

        if ($price === null) {
            return null;
        }

        return (int) round((float) $price * 100);
    }

    private function extractDescription(array $product): ?string
    {
        $html = $product['body_html'] ?? null;

        if (! $html) {
            return null;
        }

        return trim(strip_tags($html)) ?: null;
    }

    /**
     * Parse character/model names and quantities from the "Contents:" section of body_html.
     *
     * @return array<int, array{name: string, quantity: int}>
     */
    private function extractContentItems(string $html): array
    {
        if (empty($html)) {
            return [];
        }

        // Find the Contents section - everything after "Contents:" until the next <p> or end
        if (! preg_match('/Contents:<\/strong>(.+?)(?:<\/p>|$)/si', $html, $match)) {
            return [];
        }

        $contentsBlock = $match[1];

        // Split on bullet points (• or <br>) and clean up
        $rawItems = preg_split('/[•·]|<br\s*\/?>/', $contentsBlock);
        if ($rawItems === false) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $item) {
            $cleaned = trim(strip_tags($item));

            // Extract quantity before removing it (e.g. "x3", "(x3)", "x 3")
            $quantity = 1;
            if (preg_match('/\s*\(?x\s?(\d+)\)?\s*$/i', $cleaned, $qtyMatch)) {
                $quantity = (int) $qtyMatch[1];
                $cleaned = (string) preg_replace('/\s*\(?x\s?\d+\)?\s*$/i', '', $cleaned);
            }

            // Strip "Counts as ..." parentheticals
            $cleaned = (string) preg_replace('/\s*\(.*?\)\s*$/', '', $cleaned);
            $cleaned = trim($cleaned);

            // Skip empty, boilerplate, and non-character entries
            if (empty($cleaned) || strlen($cleaned) < 2) {
                continue;
            }
            $lower = strtolower($cleaned);
            if (str_contains($lower, 'stat and upgrade cards')
                || str_contains($lower, 'all stat')
                || str_contains($lower, 'heroic scale')
                || str_contains($lower, 'display sculpt')
                || str_contains($lower, 'fourth edition stat')
                || str_contains($lower, 'playable 32mm')
            ) {
                continue;
            }

            $items[] = ['name' => $cleaned, 'quantity' => $quantity];
        }

        return $items;
    }

    /**
     * Match extracted content items to existing Character records, with quantity for pivot.
     *
     * @param  array<int, array{name: string, quantity: int}>  $items
     * @return array<int, array{quantity: int}>
     */
    private function matchCharactersWithQuantity(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $item['name']));

            if ($character && ! isset($syncData[$character->id])) {
                $syncData[$character->id] = ['quantity' => $item['quantity']];
            }
        }

        return $syncData;
    }

    /**
     * Check if a character display_name matches a content name.
     * Handles exact match and common variations.
     */
    private function namesMatch(string $displayName, string $contentName): bool
    {
        $normalize = fn (string $s): string => strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $s) ?? $s));

        // Exact match after normalization
        if ($normalize($displayName) === $normalize($contentName)) {
            return true;
        }

        // Check if display_name starts with content name (handles "Sonnia Criid" matching "Sonnia Criid, Unrelenting")
        if (str_starts_with($normalize($displayName), $normalize($contentName))) {
            return true;
        }

        // Check if content name starts with display_name
        if (str_starts_with($normalize($contentName), $normalize($displayName))) {
            return true;
        }

        return false;
    }

    private function syncStoreLink(Package $package, string $url): void
    {
        PackageStoreLink::updateOrCreate(
            [
                'package_id' => $package->id,
                'store_name' => 'Wyrd Games',
            ],
            [
                'url' => $url,
                'sort_order' => 0,
            ],
        );
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function detectCategory(string $name, array $tags): PackageCategoryEnum
    {
        $lower = strtolower($name);

        if (str_starts_with($lower, 'alt ') || str_contains($lower, 'alt ')) {
            return PackageCategoryEnum::Alternate;
        }

        if (str_contains($lower, 'iconic')) {
            return PackageCategoryEnum::Iconic;
        }

        if (str_contains($lower, 'nightmare') || in_array('Nightmare', $tags)) {
            return PackageCategoryEnum::Nightmare;
        }

        if (str_contains($lower, 'title')) {
            return PackageCategoryEnum::Title;
        }

        if (str_contains($lower, 'fate deck')
            || str_contains($lower, 'measuring')
            || str_contains($lower, 'tokens')
            || str_contains($lower, 'base')
            || str_contains($lower, 't-shirt')
            || str_contains($lower, 'terrain')
        ) {
            return PackageCategoryEnum::Accessories;
        }

        // "Malifaux Fourth Edition: Name" or "Malifaux Third Edition: Name" pattern = Core Box
        if (preg_match('/malifaux\s+(third|fourth)\s+edition:/i', $name)) {
            return PackageCategoryEnum::CoreBox;
        }

        return PackageCategoryEnum::Expansion;
    }

    private function downloadImage(string $url, string $slug, string $side): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                $this->warn("    Failed to download {$side} image for {$slug}");

                return null;
            }

            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $filename = "{$slug}_{$side}.{$extension}";
            $path = "packages/{$slug}/{$filename}";

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            $this->warn("    Image download error for {$slug}: ".$e->getMessage());

            return null;
        }
    }
}
