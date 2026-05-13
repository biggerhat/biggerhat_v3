<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Builds the per-page social-meta payload handed to the Blade root view via
 * `->withViewData(['page_meta' => ...])`. The Blade template renders these
 * into <title>, <meta name="description">, <meta property="og:*">, and
 * <meta name="twitter:*"> so link unfurlers (Discord, Slack, Twitter)
 * see the right title/description/image for the specific page — Vue's
 * <SeoHead> only kicks in after hydration, which crawlers never trigger.
 *
 * Unrelated to the App\Models\Meta model (which is player community
 * groupings); the unfortunate naming collision is contained to this one
 * key in the view-data array.
 */
trait BuildsPageMeta
{
    /**
     * @param  string  $title  Bare title; "— BiggerHat" suffix is appended here so every page gets it consistently
     * @param  string|null  $description  Plain text or HTML — stripped + clamped to ~200 chars
     * @param  string|null  $image  Absolute URL, root-relative path, or storage-disk path
     * @param  'website'|'article'|'profile'  $type
     * @return array{title: string, description: string|null, image: string|null, type: string}
     */
    protected function pageMeta(string $title, ?string $description = null, ?string $image = null, string $type = 'website'): array
    {
        return [
            'title' => trim($title).' — BiggerHat',
            'description' => $description !== null
                ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? ''), 200)
                : null,
            'image' => $image !== null ? $this->resolveAbsoluteImageUrl($image) : null,
            'type' => $type,
        ];
    }

    /**
     * Resolve any image reference to an absolute URL the way social
     * scrapers expect (they refuse to fetch relative or storage paths).
     */
    private function resolveAbsoluteImageUrl(string $image): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        if (str_starts_with($image, '/')) {
            return url($image);
        }

        return url('/storage/'.ltrim($image, '/'));
    }
}
