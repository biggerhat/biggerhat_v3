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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWyrdBlueprints extends Command
{
    protected $signature = 'app:import-wyrd-blueprints
        {--dry-run : Show what would be imported without saving}
        {--force : Update existing blueprints instead of skipping}
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
            match ($result) {
                'created' => $created++,
                'updated' => $updated++,
                default => $skipped++,
            };
        }

        $this->newLine();
        $this->info(sprintf('Done! Created: %d, Updated: %d, Skipped: %d', $created, $updated, $skipped));

        return self::SUCCESS;
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

        // Match blog post article blocks - Squarespace uses article elements with entry-title links
        // Pattern: find post URLs from the listing page
        if (! preg_match_all('/<a[^>]+href="(\/plastic-build-instructions\/(\d{4})\/(\d{1,2})\/(\d{1,2})\/([^"]+))"[^>]*>/i', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $seen = [];
        foreach ($matches as $match) {
            $path = $match[1];
            $slug = $match[5];

            // Skip duplicates on the same page
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

    /**
     * Extract the "Older Posts" pagination link.
     */
    private function extractOlderPostsLink(string $html): ?string
    {
        // Squarespace uses ?offset= for pagination
        if (preg_match('/href="(\/plastic-build-instructions\?offset=\d+)"[^>]*>.*?Older/si', $html, $match)) {
            return $match[1];
        }

        // Also check for a "next" pagination link
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

        // Extract title from og:title or entry-title
        $title = '';
        if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            // Strip site name suffix (e.g. " — Wyrd Games")
            $title = (string) preg_replace('/\s*[—–\-]+\s*Wyrd Games\s*$/i', '', $title);
        } elseif (preg_match('/<h1[^>]*class="[^"]*entry-title[^"]*"[^>]*>(.*?)<\/h1>/si', $html, $m)) {
            $title = trim(strip_tags($m[1]));
        }

        // Extract images from Squarespace CDN — only build instruction images
        $images = [];
        if (preg_match_all('/https:\/\/images\.squarespace-cdn\.com\/content\/v1\/[^"\'>\s]+/i', $html, $imgMatches)) {
            foreach ($imgMatches[0] as $img) {
                $cleanImg = preg_replace('/\?.*$/', '', $img);
                if (! $cleanImg || in_array($cleanImg, $images)) {
                    continue;
                }
                // Filter out non-blueprint images
                $basename = basename($cleanImg);
                if ($this->isJunkImage($basename)) {
                    continue;
                }
                $images[] = $cleanImg;
            }
        }

        // Extract tags from Squarespace tag links (both ?tag= and /tag/ formats)
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

        // Also try meta keywords
        if (preg_match('/<meta\s+name="keywords"\s+content="([^"]+)"/i', $html, $kwMatch)) {
            foreach (explode(',', $kwMatch[1]) as $kw) {
                $kw = trim($kw);
                if ($kw && ! in_array($kw, $tags)) {
                    $tags[] = $kw;
                }
            }
        }

        // Extract categories — nav filter links appear once (in the nav), actual post
        // categories appear a second time in the article metadata. Count occurrences
        // and only include categories that appear more than once on the page.
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
        // Categories appearing 2+ times are actually assigned to the post
        foreach ($categoryCounts as $cat => $count) {
            if ($count >= 2) {
                $categories[] = $cat;
            }
        }

        // Extract SKU from image filenames (e.g., WYR24103)
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

    private function processPost(array $post): string
    {
        $slug = $post['slug'];

        $existing = Blueprint::where('wyrd_post_slug', $slug)->first();

        if ($existing && ! $this->option('force')) {
            $this->line("  <comment>SKIP</comment> {$slug} (already exists)");

            return 'skipped';
        }

        // Fetch full post details
        $details = $this->fetchPostDetails($post['url']);
        usleep(500000); // Be polite

        $title = $details['title'] ?: $post['title'];
        $tags = $details['tags'];
        $categories = $details['categories'];
        $images = $details['images'];
        $sku = $details['sku'];

        // Determine sculpt version from categories
        $sculptVersion = SculptVersionEnum::ThirdEdition;
        foreach ($categories as $category) {
            if (isset(self::EDITION_CATEGORY_MAP[$category])) {
                $sculptVersion = self::EDITION_CATEGORY_MAP[$category];
            }
        }
        // Fourth edition takes priority
        if (in_array('Malifaux Fourth Edition', $categories)) {
            $sculptVersion = SculptVersionEnum::FourthEdition;
        }

        $publishedAt = $post['date'] ? Carbon::parse($post['date']) : null;

        // Match characters from tags
        $characterIds = $this->matchCharacters($tags, $title);
        // Match miniatures from tags and image filenames
        $miniatureIds = $this->matchMiniatures($tags, $images);
        // Match packages by SKU or name
        $packageIds = $this->matchPackages($sku, $title, $tags);

        if ($this->option('dry-run')) {
            $this->line(sprintf(
                '  <info>%s</info> %s | %s | %d images | %s',
                $existing ? 'UPDATE' : 'CREATE',
                $title,
                $sculptVersion->label(),
                count($images),
                $publishedAt?->format('Y-m-d') ?? 'no date',
            ));

            if (! empty($characterIds)) {
                $charNames = $this->allCharacters->whereIn('id', $characterIds)->pluck('display_name')->join(', ');
                $this->line("    <info>Characters:</info> {$charNames}");
            }
            if (! empty($miniatureIds)) {
                $miniNames = $this->allMiniatures->whereIn('id', $miniatureIds)->pluck('display_name')->join(', ');
                $this->line("    <info>Miniatures:</info> {$miniNames}");
            }
            if (! empty($packageIds)) {
                $pkgNames = $this->allPackages->whereIn('id', $packageIds)->pluck('name')->join(', ');
                $this->line("    <info>Packages:</info> {$pkgNames}");
            }
            if (! empty($tags)) {
                $this->line('    <comment>Tags:</comment> '.implode(', ', $tags));
            }

            return $existing ? 'updated' : 'created';
        }

        $sourceUrl = self::BASE_URL.$post['url'];

        $data = [
            'name' => $title,
            'slug' => Str::slug($title),
            'image' => $images[0] ?? null,
            'images' => $images,
            'source_url' => $sourceUrl,
            'wyrd_post_slug' => $slug,
            'sculpt_version' => $sculptVersion,
            'published_at' => $publishedAt,
        ];

        if ($existing) {
            $existing->update($data);
            $existing->characters()->sync($characterIds);
            $existing->miniatures()->sync($miniatureIds);
            $existing->packages()->sync($packageIds);
            $this->downloadImages($existing);
            $this->line("  <info>UPDATED</info> {$title}");

            return 'updated';
        }

        $blueprint = Blueprint::create($data);
        $blueprint->characters()->sync($characterIds);
        $blueprint->miniatures()->sync($miniatureIds);
        $blueprint->packages()->sync($packageIds);
        $this->downloadImages($blueprint);
        $this->line("  <info>CREATED</info> {$title}");

        return 'created';
    }

    /**
     * Match characters from post tags and title.
     *
     * @param  string[]  $tags
     * @return int[]
     */
    private function matchCharacters(array $tags, string $title): array
    {
        $ids = [];

        // Try matching tags against character names
        foreach ($tags as $tag) {
            $character = $this->allCharacters->first(fn (Character $c) => $this->namesMatch($c->display_name, $tag));

            if ($character && ! in_array($character->id, $ids)) {
                $ids[] = $character->id;
            }
        }

        // Also try the post title (often contains the master's name)
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
     * Match miniatures from tags and image filenames.
     *
     * @param  string[]  $tags
     * @param  string[]  $images
     * @return int[]
     */
    private function matchMiniatures(array $tags, array $images): array
    {
        $ids = [];

        // Extract names from image filenames (e.g. "WYR24103-Sonnia-Criid-Unrelenting-Front.png")
        $imageNames = [];
        foreach ($images as $img) {
            $basename = pathinfo(parse_url($img, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
            // Remove SKU prefix and -Front/-Back/-Side suffixes
            $cleaned = (string) preg_replace('/^WYR\d+-/', '', $basename);
            $cleaned = (string) preg_replace('/-(Front|Back|Side-?\d?)$/i', '', $cleaned);
            $cleaned = str_replace('-', ' ', $cleaned);
            if (strlen($cleaned) >= 3) {
                $imageNames[] = $cleaned;
            }
        }

        // Combine tags and image names for matching
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
     * Match packages by SKU extracted from images or by title/tags.
     *
     * @param  string[]  $tags
     * @return int[]
     */
    private function matchPackages(?string $sku, string $title, array $tags): array
    {
        $ids = [];

        // Try SKU match first
        if ($sku) {
            $package = $this->allPackages->first(fn (Package $p) => $p->sku && stripos($p->sku, $sku) !== false);

            if ($package) {
                $ids[] = $package->id;
            }
        }

        // Try matching by title
        if (empty($ids)) {
            $package = $this->allPackages->first(fn (Package $p) => $this->namesMatch($p->name, $title));

            if ($package) {
                $ids[] = $package->id;
            }
        }

        // Try matching individual tags
        foreach ($tags as $tag) {
            // Skip generic tags
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

    /**
     * Download CDN images to local storage and update the blueprint record.
     */
    private function downloadImages(Blueprint $blueprint): void
    {
        $images = $blueprint->images ?? [];
        $localImages = [];
        $changed = false;

        foreach ($images as $imageUrl) {
            if (! str_starts_with($imageUrl, 'http')) {
                $localImages[] = $imageUrl;

                continue;
            }

            $basename = $this->sanitizeFilename(basename(parse_url($imageUrl, PHP_URL_PATH) ?: ''));
            if (! $basename) {
                continue;
            }

            $localPath = "blueprints/{$blueprint->id}/{$basename}";

            if (Storage::disk('public')->exists($localPath)) {
                $localImages[] = $localPath;
                $changed = true;

                continue;
            }

            try {
                $response = Http::timeout(30)->get($imageUrl);
                if ($response->successful()) {
                    Storage::disk('public')->put($localPath, $response->body());
                    $localImages[] = $localPath;
                    $changed = true;
                } else {
                    $localImages[] = $imageUrl;
                }
            } catch (\Exception) {
                $localImages[] = $imageUrl;
            }

            usleep(100000);
        }

        if ($changed) {
            $blueprint->update([
                'images' => $localImages,
                'image' => $localImages[0] ?? null,
            ]);
        }
    }

    /**
     * Determine if an image basename is a non-blueprint junk image (site chrome, favicons, etc.).
     */
    private function isJunkImage(string $basename): bool
    {
        // Exact matches for recurring site images
        $junkNames = [
            'favicon.ico',
            'image+63.png',
            'image+13.png',
        ];

        if (in_array($basename, $junkNames, true)) {
            return true;
        }

        // image-asset.jpeg/png are Squarespace placeholder images
        if (str_starts_with($basename, 'image-asset.')) {
            return true;
        }

        return false;
    }

    /**
     * Fuzzy name matching — same logic as ImportWyrdPackages.
     */
    private function namesMatch(string $displayName, string $contentName): bool
    {
        $normalize = fn (string $s): string => strtolower(trim(preg_replace('/[^a-z0-9\s]/i', '', $s) ?? $s));

        $a = $normalize($displayName);
        $b = $normalize($contentName);

        if ($a === $b) {
            return true;
        }

        if (str_starts_with($a, $b) || str_starts_with($b, $a)) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize a CDN filename: decode URL encoding, replace spaces/plus with hyphens.
     */
    private function sanitizeFilename(string $filename): string
    {
        $decoded = urldecode($filename);
        $cleaned = (string) preg_replace('/[\s+]+/', '-', $decoded);
        $cleaned = (string) preg_replace('/[()]+/', '', $cleaned);
        $cleaned = (string) preg_replace('/-{2,}/', '-', $cleaned);

        return trim($cleaned, '-');
    }
}
