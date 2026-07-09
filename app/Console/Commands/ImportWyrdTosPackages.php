<?php

namespace App\Console\Commands;

use App\Enums\GameSystemEnum;
use App\Enums\PackageCategoryEnum;
use App\Models\Character;
use App\Models\Package;
use App\Models\PackageStoreLink;
use App\Models\TOS\Unit;
use App\Traits\Console\FetchesWyrdShopifyProducts;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWyrdTosPackages extends Command
{
    use FetchesWyrdShopifyProducts;

    protected $signature = 'app:import-wyrd-tos-packages
        {--dry-run : Show what would be imported without saving}
        {--skip-images : Skip downloading product images}
        {--force : Update existing packages instead of skipping}
        {--from-file= : Import from a JSON file previously written by --dump-to instead of fetching from Wyrd (for hosts Wyrd blocks)}
        {--dump-to= : After fetching (or loading via --from-file), write the raw product list to this file}';

    protected $description = 'Import packages from the Wyrd Miniatures Shopify store — The Other Side collection';

    private const BASE_URL = 'https://giveusyourmoneypleasethankyou-wyrd.com';

    private const COLLECTION_HANDLE = 'the-other-side';

    // Tags that mark an otherwise-TOS product as ALSO playable in Malifaux
    // (crossover minis like Hex Bows/Yaksha, or dual-game starter boxes).
    private const MALIFAUX_CROSSOVER_TAGS = ['Malifaux', 'M3E', 'M3e', 'M4E', 'M4e'];

    // "Contains" list items that describe box filler rather than a model —
    // mirrors the boilerplate skip-list in ImportWyrdPackages.
    private const BOILERPLATE_NEEDLES = [
        'preassembled minis', 'fate deck', 'tactics token', 'base', 'measuring tape',
        'quick start guide', 'stat card',
    ];

    private const CONTENT_LABELS = 'Adjunct Model|Champion|Commander Unit|Commander|Titan|Units?';

    /** @var Collection<int, Unit> */
    private Collection $allUnits;

    /** @var Collection<int, Character> */
    private Collection $allCharacters;

    public function handle(): int
    {
        $this->info('Fetching The Other Side products from Wyrd store...');

        $this->allUnits = Unit::all();
        $this->allCharacters = Character::all();

        $products = $this->resolveWyrdProducts(self::BASE_URL.'/collections/'.self::COLLECTION_HANDLE.'/products.json');

        $this->info(sprintf('Found %d TOS products.', count($products)));

        if (count($products) === 0) {
            $this->warn('No TOS products found.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $linked = 0;
        $totalUnitsLinked = 0;
        $totalCharactersLinked = 0;

        foreach ($products as $product) {
            [$result, $unitsLinked, $charactersLinked] = $this->processProduct($product);
            $totalUnitsLinked += $unitsLinked;
            $totalCharactersLinked += $charactersLinked;
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $linked++,
            };
        }

        $this->newLine();
        $this->info(sprintf(
            'Done! Created: %d, Updated: %d, Linked-only: %d, Units linked: %d, Characters linked: %d',
            $created,
            $updated,
            $linked,
            $totalUnitsLinked,
            $totalCharactersLinked,
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{string, int, int}
     */
    private function processProduct(array $product): array
    {
        $sku = $product['variants'][0]['sku'] ?? null;
        $name = $product['title'] ?? '';
        $slug = $product['handle'] ?? Str::slug($name);
        $html = $product['body_html'] ?? '';

        $tags = $product['tags'] ?? [];
        if (is_string($tags)) {
            $tags = array_map('trim', explode(',', $tags));
        }

        $contentItems = $this->extractContentItems($html);
        $unitSyncData = $this->matchUnitsWithQuantity($contentItems);
        $characterSyncData = $this->matchCharactersWithQuantity($contentItems);

        // Crossover minis often only name their Malifaux alias in the
        // description prose (e.g. "compatible with Malifaux Third Edition
        // as Datsue Ba"), not in the Contains list.
        $characterSyncData += $this->matchAliasedCharacter($html);

        $isCrossover = ! empty(array_intersect($tags, self::MALIFAUX_CROSSOVER_TAGS)) || ! empty($characterSyncData);
        $gameSystem = $isCrossover ? GameSystemEnum::Both : GameSystemEnum::Tos;

        $existing = Package::where('slug', $slug)
            ->when($sku, fn ($q) => $q->orWhere('sku', $sku))
            ->first();

        // Never let this product's own tags decide the final game_system
        // for a row we already know about — a Malifaux row is always
        // promoted to Both (it now provably exists in the TOS collection
        // too) and an already-Both row must never regress, regardless of
        // whether --force is passed.
        $finalGameSystem = match ($existing?->game_system) {
            GameSystemEnum::Malifaux, GameSystemEnum::Both => GameSystemEnum::Both,
            default => $gameSystem,
        };

        $category = $this->detectCategory($product['product_type'] ?? '');
        $price = $this->extractPrice($product);
        $description = $this->extractDescription($html);
        $storeUrl = self::BASE_URL.'/products/'.$slug;

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  <info>%s</info> %s | SKU: %s | $%s | %s | %s',
                $existing ? ($this->option('force') ? 'UPDATE' : 'LINK') : 'CREATE',
                $name,
                $sku ?? 'N/A',
                $price ? number_format($price / 100, 2) : 'N/A',
                $finalGameSystem->label(),
                $category->label(),
            ));
            foreach ($contentItems as $item) {
                $unitMatch = $this->allUnits->first(fn (Unit $u) => $this->namesMatch($u->name, $item['name']));
                $charMatch = $unitMatch ? null : $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $item['name']));
                $qty = $item['quantity'] > 1 ? " (x{$item['quantity']})" : '';
                if ($unitMatch) {
                    $this->line("    <info>✓</info> {$item['name']}{$qty} → Unit: {$unitMatch->name}");
                } elseif ($charMatch) {
                    $this->line("    <info>✓</info> {$item['name']}{$qty} → Character: {$charMatch->display_name}");
                } else {
                    $this->line("    <comment>?</comment> {$item['name']}{$qty} → no match");
                }
            }

            return [$existing ? 'updated' : 'created', count($unitSyncData), count($characterSyncData)];
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
            'game_system' => $finalGameSystem,
            'factions' => [],
            'description' => $description,
            'msrp' => $price,
            'sku' => $sku,
            'category' => $category,
            'is_preassembled' => true,
        ];

        if (! $this->option('skip-images')) {
            if ($frontImage) {
                $data['front_image'] = $frontImage;
            }
            if ($backImage) {
                $data['back_image'] = $backImage;
            }
        }

        if ($existing) {
            // Don't clobber Malifaux-owned fields (name/description/images/
            // etc.) unless --force is passed — but always merge in the
            // TOS-side linkage and game_system, since an existing Malifaux
            // row for a crossover product must not stay invisible on TOS
            // pages just because it predates this importer.
            if ($this->option('force')) {
                $existing->update($data);
            } elseif ($existing->game_system !== $finalGameSystem) {
                $existing->update(['game_system' => $finalGameSystem]);
            }

            $existing->tosUnits()->sync($unitSyncData);
            if (! empty($characterSyncData)) {
                $existing->characters()->syncWithoutDetaching($characterSyncData);
            }
            $this->syncStoreLink($existing, $storeUrl);

            $verb = $this->option('force') ? 'UPDATED' : 'LINKED';
            $this->line("  <info>{$verb}</info> {$name} (".count($unitSyncData).' units, '.count($characterSyncData).' characters)');

            return [$this->option('force') ? 'updated' : 'linked', count($unitSyncData), count($characterSyncData)];
        }

        $package = Package::create($data);
        $package->tosUnits()->sync($unitSyncData);
        if (! empty($characterSyncData)) {
            $package->characters()->syncWithoutDetaching($characterSyncData);
        }
        $this->syncStoreLink($package, $storeUrl);
        $this->line("  <info>CREATED</info> {$name} (".count($unitSyncData).' units, '.count($characterSyncData).' characters)');

        return ['created', count($unitSyncData), count($characterSyncData)];
    }

    /**
     * Parse model/unit names and quantities out of the "Contains" section of
     * body_html. Wyrd's TOS product pages use two different shapes for this:
     * a labeled `Label : Name [N models]` list item, and — mostly on
     * crossover minis — a bare `Name x3` / `Name` item with no label.
     *
     * @return array<int, array{name: string, quantity: int}>
     */
    private function extractContentItems(string $html): array
    {
        if (empty($html)) {
            return [];
        }

        $containsIdx = stripos($html, 'Contains');
        if ($containsIdx === false) {
            return [];
        }

        $tail = substr($html, $containsIdx);

        if (! preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $tail, $matches)) {
            return [];
        }

        $items = [];
        foreach ($matches[1] as $raw) {
            $text = html_entity_decode(trim(strip_tags($raw)), ENT_QUOTES | ENT_HTML5);
            $text = str_replace("\u{A0}", ' ', $text); // Wyrd's editor uses non-breaking spaces, which \s won't collapse
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

            if ($text === '' || strlen($text) < 2) {
                continue;
            }

            $lower = strtolower($text);
            if (collect(self::BOILERPLATE_NEEDLES)->contains(fn ($needle) => str_contains($lower, $needle))) {
                continue;
            }

            $item = $this->parseContentLine($text);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array{name: string, quantity: int}|null
     */
    private function parseContentLine(string $text): ?array
    {
        // Shape A: "2 Units : Mehal Sefari [18 models]" / "Champion : Binh Nguyen"
        if (preg_match('/^(?:(\d+)\s*)?(?:'.self::CONTENT_LABELS.')\s*:\s*(.+?)\s*(?:\[.*\])?$/i', $text, $m)) {
            $name = trim($m[2]);
            if ($name === '') {
                return null;
            }

            return ['name' => $name, 'quantity' => $m[1] !== '' ? (int) $m[1] : 1];
        }

        // Shape B: "Hex Bows x3" / "Guild Mages (x3)" / "John Watson"
        if (preg_match('/^(.*?)\s*\(?x\s?(\d+)\)?$/i', $text, $m) && trim($m[1]) !== '') {
            return ['name' => trim($m[1]), 'quantity' => (int) $m[2]];
        }

        return ['name' => $text, 'quantity' => 1];
    }

    /**
     * @param  array<int, array{name: string, quantity: int}>  $items
     * @return array<int, array{quantity: int}>
     */
    private function matchUnitsWithQuantity(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            $unit = $this->allUnits->first(fn (Unit $u) => $this->namesMatch($u->name, $item['name']));

            if ($unit && ! isset($syncData[$unit->id])) {
                $syncData[$unit->id] = ['quantity' => $item['quantity']];
            }
        }

        return $syncData;
    }

    /**
     * Content items that don't match a TOS Unit are tried against Malifaux
     * Characters — dual-game starter boxes mix both in the same list.
     *
     * @param  array<int, array{name: string, quantity: int}>  $items
     * @return array<int, array{quantity: int}>
     */
    private function matchCharactersWithQuantity(array $items): array
    {
        $syncData = [];

        foreach ($items as $item) {
            if ($this->allUnits->first(fn (Unit $u) => $this->namesMatch($u->name, $item['name']))) {
                continue;
            }

            $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $item['name']));

            if ($character && ! isset($syncData[$character->id])) {
                $syncData[$character->id] = ['quantity' => $item['quantity']];
            }
        }

        return $syncData;
    }

    /**
     * Some crossover minis only name their Malifaux alias in prose, e.g.
     * "This Model is compatible with Malifaux Third Edition as Datsue Ba".
     *
     * @return array<int, array{quantity: int}>
     */
    private function matchAliasedCharacter(string $html): array
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        if (! preg_match('/compatible with Malifaux (?:Third|Fourth) Edition as ([A-Z][\p{L}\'\-]*(?:\s[A-Z][\p{L}\'\-]*)*)/u', $text, $m)) {
            return [];
        }

        $alias = trim($m[1]);
        $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $alias));

        return $character ? [$character->id => ['quantity' => 1]] : [];
    }

    /**
     * Check if a name matches a content name, allowing common variations
     * (subtitle suffixes, punctuation differences).
     */
    private function namesMatch(string $a, string $b): bool
    {
        $normalize = fn (string $s): string => strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $s) ?? $s));

        $normA = $normalize($a);
        $normB = $normalize($b);

        if ($normA === $normB) {
            return true;
        }

        return str_starts_with($normA, $normB) || str_starts_with($normB, $normA);
    }

    private function extractPrice(array $product): ?int
    {
        $price = $product['variants'][0]['price'] ?? null;

        if ($price === null) {
            return null;
        }

        return (int) round((float) $price * 100);
    }

    private function extractDescription(string $html): ?string
    {
        if (! $html) {
            return null;
        }

        $containsIdx = stripos($html, '<p><strong>Contains</strong></p>');
        $prose = $containsIdx !== false ? substr($html, 0, $containsIdx) : $html;

        return trim(strip_tags($prose)) ?: null;
    }

    private function detectCategory(string $productType): PackageCategoryEnum
    {
        $lower = strtolower($productType);

        return match (true) {
            str_contains($lower, 'allegiance') || str_contains($lower, 'starter') => PackageCategoryEnum::CoreBox,
            str_contains($lower, 'fate deck') => PackageCategoryEnum::Accessories,
            str_contains($lower, 'book') => PackageCategoryEnum::Other,
            default => PackageCategoryEnum::Expansion,
        };
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
