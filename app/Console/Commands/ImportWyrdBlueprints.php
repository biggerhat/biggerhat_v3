<?php

namespace App\Console\Commands;

use App\Enums\SculptVersionEnum;
use App\Models\Blueprint;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWyrdBlueprints extends Command
{
    protected $signature = 'app:import-wyrd-blueprints
        {--dry-run : Show what would be imported without saving}
        {--force : Update existing blueprints instead of skipping}
        {--fresh : Delete all blueprints and images before importing}
        {--limit=0 : Limit the number of pages to scrape (0 = all)}';

    protected $description = 'Import build instructions (blueprints) from the Wyrd Games website';

    private const BASE_URL = 'https://www.wyrd-games.net';

    private const BLOG_PATH = '/plastic-build-instructions';

    private const EDITION_CATEGORY_MAP = [
        'Malifaux Fourth Edition' => SculptVersionEnum::FourthEdition,
        'Malifaux' => SculptVersionEnum::ThirdEdition,
    ];

    /** @var Collection<int, Character> */
    private Collection $allCharacters;

    /** @var Collection<int, Miniature> */
    private Collection $allMiniatures;

    /** @var Collection<int, Package> */
    private Collection $allPackages;

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->freshStart();
        }

        $this->info('Fetching build instructions from Wyrd Games...');

        $this->allCharacters = Character::all();
        $this->allMiniatures = Miniature::all();
        $this->allPackages = Package::all();

        $posts = $this->fetchAllPosts();

        $this->info(sprintf('Found %d build instruction posts.', count($posts)));

        if (count($posts) === 0) {
            $this->warn('No posts found.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($posts as $post) {
            $result = $this->processPost($post);
            $created += $result['created'];
            $updated += $result['updated'];
            $skipped += $result['skipped'];
        }

        $this->newLine();
        $this->info(sprintf('Done! Created: %d, Updated: %d, Skipped: %d', $created, $updated, $skipped));

        return self::SUCCESS;
    }

    private function freshStart(): void
    {
        $this->warn('Clearing all blueprints, images, and pivot data...');

        // Clean morph pivot tables for Blueprint entries
        DB::table('characterables')->where('characterable_type', Blueprint::class)->delete();
        DB::table('miniatureables')->where('miniatureable_type', Blueprint::class)->delete();
        DB::table('packageables')->where('packageable_type', Blueprint::class)->delete();

        Blueprint::withTrashed()->forceDelete();

        // Remove downloaded image files
        Storage::disk('public')->deleteDirectory('blueprints');

        $this->info('Cleared all blueprint data.');
    }

    /**
     * Paginate through the blog and collect post metadata from listing pages.
     *
     * @return array<int, array{title: string, slug: string, url: string, date: string|null, categories: string[], tags: string[]}>
     */
    private function fetchAllPosts(): array
    {
        $allPosts = [];
        $url = self::BASE_URL.self::BLOG_PATH;
        $pageNum = 1;
        $limit = (int) $this->option('limit');

        while ($url) {
            $this->line("  Fetching page {$pageNum}...");

            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                $this->error("Failed to fetch page {$pageNum}: ".$response->status());
                break;
            }

            $html = $response->body();
            $posts = $this->parseListingPage($html);

            if (empty($posts)) {
                break;
            }

            $allPosts = array_merge($allPosts, $posts);

            // Find "Older Posts" pagination link
            $nextUrl = $this->extractOlderPostsLink($html);
            $url = $nextUrl ? self::BASE_URL.$nextUrl : null;
            $pageNum++;

            if ($limit > 0 && $pageNum > $limit) {
                $this->line("  Reached page limit of {$limit}.");
                break;
            }

            // Be polite to the server
            usleep(500000);
        }

        return $allPosts;
    }

    /**
     * Parse post entries from a listing page.
     *
     * @return array<int, array{title: string, slug: string, url: string, date: string|null, categories: string[], tags: string[]}>
     */
    private function parseListingPage(string $html): array
    {
        $posts = [];

        if (! preg_match_all('/<a[^>]+href="(\/plastic-build-instructions\/(\d{4})\/(\d{1,2})\/(\d{1,2})\/([^"]+))"[^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $seen = [];
        foreach ($matches as $match) {
            $path = $match[1];
            $slug = $match[5];

            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            $year = $match[2];
            $month = $match[3];
            $day = $match[4];

            $posts[] = [
                'title' => Str::headline(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'url' => $path,
                'date' => "{$year}-{$month}-{$day}",
                'categories' => [],
                'tags' => [],
            ];
        }

        return $posts;
    }

    private function extractOlderPostsLink(string $html): ?string
    {
        if (preg_match('/href="(\/plastic-build-instructions\?offset=\d+)"[^>]*>.*?Older/si', $html, $match)) {
            return $match[1];
        }

        if (preg_match('/class="[^"]*older[^"]*"[^>]*href="([^"]+)"/i', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Fetch an individual post page and extract full details.
     *
     * @return array{title: string, images: string[], tags: string[], categories: string[], sku: string|null}
     */
    private function fetchPostDetails(string $url): array
    {
        $fullUrl = self::BASE_URL.$url;
        $response = Http::timeout(30)->get($fullUrl);

        if (! $response->successful()) {
            $this->warn("    Failed to fetch post: {$url}");

            return ['title' => '', 'images' => [], 'tags' => [], 'categories' => [], 'sku' => null];
        }

        $html = $response->body();

        $title = '';
        if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $title = (string) preg_replace('/\s*[—–\-]+\s*Wyrd Games\s*$/i', '', $title);
        } elseif (preg_match('/<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            $title = trim(strip_tags($m[1]));
        }

        $images = [];
        if (preg_match_all('/https:\/\/images\.squarespace-cdn\.com\/content\/v1\/[^"\'>\s]+/i', $html, $imgMatches)) {
            foreach ($imgMatches[0] as $img) {
                $cleanImg = preg_replace('/\?.*$/', '', $img);
                if (! $cleanImg || in_array($cleanImg, $images)) {
                    continue;
                }
                $basename = basename($cleanImg);
                if ($this->isJunkImage($basename)) {
                    continue;
                }
                $images[] = $cleanImg;
            }
        }

        $tags = [];
        if (preg_match_all('/<a[^>]+href="\/plastic-build-instructions\/tag\/([^"]+)"/i', $html, $tagMatches)) {
            foreach ($tagMatches[1] as $tag) {
                $decoded = urldecode(str_replace('+', ' ', $tag));
                if (! in_array($decoded, $tags)) {
                    $tags[] = $decoded;
                }
            }
        }
        if (preg_match_all('/<a[^>]+href="\/plastic-build-instructions\/?\?tag=([^"&]+)"/i', $html, $tagMatches)) {
            foreach ($tagMatches[1] as $tag) {
                $decoded = urldecode(str_replace('+', ' ', $tag));
                if (! in_array($decoded, $tags)) {
                    $tags[] = $decoded;
                }
            }
        }

        if (preg_match('/<meta\s+name="keywords"\s+content="([^"]+)"/i', $html, $kwMatch)) {
            foreach (explode(',', $kwMatch[1]) as $kw) {
                $kw = trim($kw);
                if ($kw && ! in_array($kw, $tags)) {
                    $tags[] = $kw;
                }
            }
        }

        $categories = [];
        $categoryCounts = [];
        if (preg_match_all('/<a[^>]+href="\/plastic-build-instructions\/category\/([^"]+)"/i', $html, $catMatches)) {
            foreach ($catMatches[1] as $cat) {
                $decoded = urldecode(str_replace('+', ' ', $cat));
                $categoryCounts[$decoded] = ($categoryCounts[$decoded] ?? 0) + 1;
            }
        }
        if (preg_match_all('/<a[^>]+href="\/plastic-build-instructions\/?\?category=([^"&]+)"/i', $html, $catMatches)) {
            foreach ($catMatches[1] as $cat) {
                $decoded = urldecode(str_replace('+', ' ', $cat));
                $categoryCounts[$decoded] = ($categoryCounts[$decoded] ?? 0) + 1;
            }
        }
        foreach ($categoryCounts as $cat => $count) {
            if ($count >= 2) {
                $categories[] = $cat;
            }
        }

        $sku = null;
        foreach ($images as $img) {
            if (preg_match('/(WYR\d{4,5})/', basename($img), $skuMatch)) {
                $sku = $skuMatch[1];
                break;
            }
        }

        return [
            'title' => $title,
            'images' => $images,
            'tags' => $tags,
            'categories' => $categories,
            'sku' => $sku,
        ];
    }

    /**
     * Process a single post: create one Blueprint per image.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    private function processPost(array $post): array
    {
        $slug = $post['slug'];
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $existingBlueprints = Blueprint::where('wyrd_post_slug', $slug)->get();

        if ($existingBlueprints->isNotEmpty() && ! $this->option('force') && ! $this->option('fresh')) {
            $this->line("  <comment>SKIP</comment> {$slug} ({$existingBlueprints->count()} entries already exist)");
            $counts['skipped'] = $existingBlueprints->count();

            return $counts;
        }

        // Fetch full post details
        $details = $this->fetchPostDetails($post['url']);
        usleep(500000); // Be polite

        $title = $details['title'] ?: $post['title'];
        $tags = $details['tags'];
        $categories = $details['categories'];
        $images = $details['images'];
        $sku = $details['sku'];

        if (empty($images)) {
            $this->line("  <comment>SKIP</comment> {$slug} (no images found)");
            $counts['skipped']++;

            return $counts;
        }

        // Determine sculpt version from categories
        $sculptVersion = SculptVersionEnum::ThirdEdition;
        foreach ($categories as $category) {
            if (isset(self::EDITION_CATEGORY_MAP[$category])) {
                $sculptVersion = self::EDITION_CATEGORY_MAP[$category];
            }
        }
        if (in_array('Malifaux Fourth Edition', $categories)) {
            $sculptVersion = SculptVersionEnum::FourthEdition;
        }

        $publishedAt = $post['date'] ? Carbon::parse($post['date']) : null;
        $sourceUrl = self::BASE_URL.$post['url'];

        // Post-level matching (fallback for images with generic filenames)
        $postCharacterIds = $this->matchCharacters($tags, $title);
        $postMiniatureIds = $this->matchMiniatures($tags, $images);
        $packageIds = $this->matchPackages($sku, $title, $tags);

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  <info>CREATE</info> %s | %s | %d images | %s',
                $title,
                $sculptVersion->label(),
                count($images),
                $publishedAt?->format('Y-m-d') ?? 'no date',
            ));

            foreach ($images as $imageUrl) {
                $imageCharIds = $this->matchCharactersForImage($imageUrl);
                $charIds = $this->resolveIds($imageCharIds, $postCharacterIds);
                $imageMiniIds = $this->matchMiniatureForImage($imageUrl);
                $miniIds = $this->resolveIds($imageMiniIds, $postMiniatureIds);
                $charNames = $this->allCharacters->whereIn('id', $charIds)->pluck('display_name')->join(', ');
                $miniNames = $this->allMiniatures->whereIn('id', $miniIds)->pluck('display_name')->join(', ');
                $this->line('    <info>Image:</info> '.basename($imageUrl).' → chars: '.($charNames ?: '(none)').', minis: '.($miniNames ?: '(none)'));
            }

            $counts['created'] = count($images);

            return $counts;
        }

        // Delete existing entries for this post when using --force
        if ($existingBlueprints->isNotEmpty()) {
            foreach ($existingBlueprints as $existing) {
                $existing->characters()->detach();
                $existing->miniatures()->detach();
                $existing->packages()->detach();
                $existing->forceDelete();
            }
        }

        $usedBasenames = [];

        foreach (array_values($images) as $sortOrder => $imageUrl) {
            $basename = $this->sanitizeFilename(basename(parse_url($imageUrl, PHP_URL_PATH) ?: ''));
            if (! $basename) {
                continue;
            }

            // Deduplicate basenames
            if (in_array($basename, $usedBasenames)) {
                $ext = pathinfo($basename, PATHINFO_EXTENSION);
                $name = pathinfo($basename, PATHINFO_FILENAME);
                $counter = 2;
                do {
                    $basename = "{$name}-{$counter}.{$ext}";
                    $counter++;
                } while (in_array($basename, $usedBasenames));
            }
            $usedBasenames[] = $basename;

            // Per-image character and miniature matching
            $imageCharIds = $this->matchCharactersForImage($imageUrl);
            $characterIds = $this->resolveIds($imageCharIds, $postCharacterIds);
            $imageMiniIds = $this->matchMiniatureForImage($imageUrl);
            $miniatureIdsForImage = $this->resolveIds($imageMiniIds, $postMiniatureIds);

            $blueprint = Blueprint::create([
                'name' => $title,
                'slug' => Str::slug($title).($sortOrder > 0 ? "-{$sortOrder}" : ''),
                'image_path' => $imageUrl, // Will be updated after download
                'source_url' => $sourceUrl,
                'wyrd_post_slug' => $slug,
                'sculpt_version' => $sculptVersion,
                'published_at' => $publishedAt,
            ]);

            // Download image
            $localPath = "blueprints/{$blueprint->id}/{$basename}";
            $imagePath = $this->downloadImage($imageUrl, $localPath);
            $blueprint->update(['image_path' => $imagePath]);

            $blueprint->characters()->sync($characterIds);
            $blueprint->miniatures()->sync($miniatureIdsForImage);
            $blueprint->packages()->sync($packageIds);

            $charNames = $this->allCharacters->whereIn('id', $characterIds)->pluck('display_name')->join(', ');
            $miniNames = $this->allMiniatures->whereIn('id', $miniatureIdsForImage)->pluck('display_name')->join(', ');
            $this->line("  <info>CREATED</info> {$title} → {$basename} [chars: {$charNames}, minis: {$miniNames}]");

            $counts['created']++;
        }

        return $counts;
    }

    /**
     * Download an image from a URL to local storage.
     */
    private function downloadImage(string $imageUrl, string $localPath): string
    {
        if (! str_starts_with($imageUrl, 'http')) {
            return $imageUrl;
        }

        if (Storage::disk('public')->exists($localPath)) {
            return $localPath;
        }

        try {
            $response = Http::timeout(30)->get($imageUrl);
            if ($response->successful()) {
                Storage::disk('public')->put($localPath, $response->body());

                return $localPath;
            }
        } catch (\Exception) {
            // Keep CDN URL as fallback
        }

        usleep(100000);

        return $imageUrl;
    }

    /**
     * Decide which IDs to assign to a single blueprint image.
     *
     * Priority:
     * 1. Image-level match (from filename) — always preferred
     * 2. Post-level match — only if the post has exactly 1 entry
     *    (multi-entry posts with generic filenames get no tags
     *     rather than incorrectly tagging everything)
     *
     * @param  int[]  $imageIds  IDs matched from the image filename
     * @param  int[]  $postIds  IDs matched from the post tags/title
     * @return int[]
     */
    private function resolveIds(array $imageIds, array $postIds): array
    {
        if (! empty($imageIds)) {
            return $imageIds;
        }

        if (count($postIds) === 1) {
            return $postIds;
        }

        return [];
    }

    /**
     * Clean an image filename for matching against character/miniature names.
     * e.g. "WYR24103-Sonnia-Criid-Front.png" → "Sonnia Criid"
     */
    private function cleanImageFilename(string $imageUrl): ?string
    {
        $basename = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);

        // Strip WYR SKU prefix
        $cleaned = (string) preg_replace('/^WYR\d+-?/i', '', $basename);
        // Strip Front/Back/Side suffix
        $cleaned = (string) preg_replace('/-(Front|Back|Side-?\d?)$/i', '', $cleaned);
        // Replace hyphens with spaces
        $cleaned = str_replace('-', ' ', $cleaned);

        if (strlen($cleaned) < 3) {
            return null;
        }

        // Skip generic filenames
        if (preg_match('/^image\s*asset/i', $cleaned)) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Match characters specifically from an image filename.
     *
     * @return int[]
     */
    private function matchCharactersForImage(string $imageUrl): array
    {
        $cleaned = $this->cleanImageFilename($imageUrl);
        if (! $cleaned) {
            return [];
        }

        $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $cleaned));

        return $character ? [$character->id] : [];
    }

    /**
     * Match miniatures specifically from an image filename.
     *
     * @return int[]
     */
    private function matchMiniatureForImage(string $imageUrl): array
    {
        $cleaned = $this->cleanImageFilename($imageUrl);
        if (! $cleaned) {
            return [];
        }

        $miniature = $this->allMiniatures->first(fn (Miniature $m) => $this->namesMatch($m->display_name, $cleaned));

        return $miniature ? [$miniature->id] : [];
    }

    /**
     * @param  string[]  $tags
     * @return int[]
     */
    private function matchCharacters(array $tags, string $title): array
    {
        $ids = [];

        foreach ($tags as $tag) {
            $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $tag));

            if ($character && ! in_array($character->id, $ids)) {
                $ids[] = $character->id;
            }
        }

        $titleParts = preg_split('/[,\-–—]/', $title);
        if ($titleParts) {
            foreach ($titleParts as $part) {
                $part = trim($part);
                if (strlen($part) < 3) {
                    continue;
                }

                $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $part));

                if ($character && ! in_array($character->id, $ids)) {
                    $ids[] = $character->id;
                }
            }
        }

        return $ids;
    }

    /**
     * @param  string[]  $tags
     * @param  string[]  $images
     * @return int[]
     */
    private function matchMiniatures(array $tags, array $images): array
    {
        $ids = [];

        $imageNames = [];
        foreach ($images as $img) {
            $basename = pathinfo(parse_url($img, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
            $cleaned = (string) preg_replace('/^WYR\d+-/', '', $basename);
            $cleaned = (string) preg_replace('/-(Front|Back|Side-?\d?)$/i', '', $cleaned);
            $cleaned = str_replace('-', ' ', $cleaned);
            if (strlen($cleaned) >= 3) {
                $imageNames[] = $cleaned;
            }
        }

        $candidates = array_merge($tags, $imageNames);

        foreach ($candidates as $candidate) {
            $miniature = $this->allMiniatures->first(fn (Miniature $m) => $this->namesMatch($m->display_name, $candidate));

            if ($miniature && ! in_array($miniature->id, $ids)) {
                $ids[] = $miniature->id;
            }
        }

        return $ids;
    }

    /**
     * @param  string[]  $tags
     * @return int[]
     */
    private function matchPackages(?string $sku, string $title, array $tags): array
    {
        $ids = [];

        if ($sku) {
            $package = $this->allPackages->first(fn (Package $p) => $p->sku && stripos($p->sku, $sku) !== false);

            if ($package) {
                $ids[] = $package->id;
            }
        }

        if (empty($ids)) {
            $package = $this->allPackages->first(fn (Package $p) => $this->namesMatch($p->name, $title));

            if ($package) {
                $ids[] = $package->id;
            }
        }

        foreach ($tags as $tag) {
            $lower = strtolower($tag);
            if (in_array($lower, ['malifaux', 'versatile', 'nightmare edition', 'story', 'alt'])) {
                continue;
            }

            $package = $this->allPackages->first(fn (Package $p) => $this->namesMatch($p->name, $tag));

            if ($package && ! in_array($package->id, $ids)) {
                $ids[] = $package->id;
            }
        }

        return $ids;
    }

    private function isJunkImage(string $basename): bool
    {
        $junkNames = [
            'favicon.ico',
            'image+63.png',
            'image+13.png',
        ];

        return in_array($basename, $junkNames, true);
    }

    /**
     * Fuzzy name matching with word-boundary awareness.
     * Prevents single-word names like "Guild" from matching multi-word names like "Guild Lawyer".
     */
    private function namesMatch(string $displayName, string $contentName): bool
    {
        $normalize = fn (string $s): string => strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $s) ?? $s));

        $a = $normalize($displayName);
        $b = $normalize($contentName);

        if ($a === $b) {
            return true;
        }

        // Only allow prefix matching if the shorter string has 2+ words
        $shorter = strlen($a) <= strlen($b) ? $a : $b;
        $longer = strlen($a) <= strlen($b) ? $b : $a;

        if (str_word_count($shorter) >= 2 && str_starts_with($longer, $shorter)) {
            // Ensure match is on a word boundary
            if (strlen($longer) === strlen($shorter) || $longer[strlen($shorter)] === ' ') {
                return true;
            }
        }

        return false;
    }

    private function sanitizeFilename(string $filename): string
    {
        $decoded = urldecode($filename);
        $cleaned = (string) preg_replace('/[\s+]+/', '-', $decoded);
        $cleaned = (string) preg_replace('/[()]+/', '', $cleaned);
        $cleaned = (string) preg_replace('/-{2,}/', '-', $cleaned);

        return trim($cleaned, '-');
    }
}
