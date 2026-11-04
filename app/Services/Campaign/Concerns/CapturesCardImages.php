<?php

namespace App\Services\Campaign\Concerns;

use App\Events\CardImageGenerated;
use Carbon\CarbonInterface;
use Spatie\Browsershot\Browsershot;

/**
 * The headless-Chrome half shared by the saved-card-image generators: screenshot one element of a live capture page,
 * and stitch a front and back PNG side by side.
 */
trait CapturesCardImages
{
    protected function capture(string $url, string $selector): string
    {
        $browsershot = Browsershot::url($url)
            ->noSandbox()
            ->select($selector)
            // CardFrontFace/CardBackFace each pick their own tarot-proportioned
            // box, tiered up (to a max of 1150x1986) as the character's own
            // content grows — Capture.vue lays both out side by side (flex
            // gap-8, p-8 outer padding), so the viewport just needs to
            // comfortably exceed two max-tier cards plus padding/gap so Chrome
            // never has to reflow either fixed-width card; the element
            // screenshot itself captures exactly whatever size each face
            // rendered at (mergeSideBySide() below reads real dimensions back
            // via getimagesize(), so front/back need not match).
            ->windowSize(2500, 2100)
            ->deviceScaleFactor(2)
            ->waitUntilNetworkIdle()
            // The capture pages size both faces to the taller one, then flag themselves ready (useEqualCardHeight).
            ->waitForSelector('[data-capture-ready="true"]')
            ->timeout(60);

        if ($node = config('services.browsershot.node_binary')) {
            $browsershot->setNodeBinary($node);
        }
        if ($npm = config('services.browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npm);
        }
        if ($chrome = config('services.browsershot.chrome_path')) {
            $browsershot->setChromePath($chrome);
        }

        return $browsershot->screenshot();
    }

    /** Side-by-side merge — same GD approach as CreateCombinationImages, PNG instead of JPEG. */
    protected function mergeSideBySide(string $frontPath, string $backPath, string $outPath): void
    {
        [$fw, $fh] = getimagesize($frontPath);
        [$bw, $bh] = getimagesize($backPath);

        $canvas = imagecreatetruecolor($fw + $bw, max($fh, $bh));
        imagecopy($canvas, imagecreatefrompng($frontPath), 0, 0, 0, 0, $fw, $fh);
        imagecopy($canvas, imagecreatefrompng($backPath), $fw, 0, 0, 0, $bw, $bh);
        imagepng($canvas, $outPath);
        imagedestroy($canvas);
    }

    /**
     * Small WebP of a rendered face for gallery tiles (a few KB instead of the 70-200 KB full render). Returns false —
     * and leaves the tiles on the full image — when GD can't do WebP or the source can't be read.
     */
    public function makeThumbnail(string $sourcePath, string $outPath, int $width = 480): bool
    {
        if (! function_exists('imagewebp') || ! is_file($sourcePath) || ! ($src = @imagecreatefrompng($sourcePath))) {
            return false;
        }

        $scaled = imagescale($src, $width, -1, IMG_BICUBIC);
        imagedestroy($src);
        if (! $scaled) {
            return false;
        }

        imagesavealpha($scaled, true);
        $ok = imagewebp($scaled, $outPath, 80);
        imagedestroy($scaled);

        return $ok;
    }

    /** Tells the owner's open pages the render is done; a socket outage must not fail a finished render. */
    protected function announce(string $kind, int $id, ?int $userId, ?CarbonInterface $generatedAt): void
    {
        if (! $userId) {
            return;
        }

        try {
            event(new CardImageGenerated($userId, $kind, $id, $generatedAt?->toIso8601String()));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
