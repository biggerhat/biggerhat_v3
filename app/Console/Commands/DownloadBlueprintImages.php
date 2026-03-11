<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadBlueprintImages extends Command
{
    protected $signature = 'app:download-blueprint-images
        {--dry-run : Show what would be downloaded without saving}
        {--force : Re-download images even if they already exist locally}';

    protected $description = 'Download blueprint images from CDN and store them locally';

    public function handle(): int
    {
        $blueprints = Blueprint::withImages()->get();
        $this->info("Processing {$blueprints->count()} blueprints...");

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($blueprints as $blueprint) {
            $localImages = [];
            $changed = false;

            foreach ($blueprint->images as $imageUrl) {
                // Already a local path
                if (! str_starts_with($imageUrl, 'http')) {
                    $localImages[] = $imageUrl;

                    continue;
                }

                $basename = $this->sanitizeFilename(basename(parse_url($imageUrl, PHP_URL_PATH) ?: ''));
                if (! $basename) {
                    $failed++;

                    continue;
                }

                $localPath = "blueprints/{$blueprint->id}/{$basename}";

                // Skip if already downloaded
                if (! $this->option('force') && Storage::disk('public')->exists($localPath)) {
                    $localImages[] = $localPath;
                    $skipped++;

                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->line("  <info>DOWNLOAD</info> {$basename} → {$localPath}");
                    $localImages[] = $localPath;
                    $downloaded++;

                    continue;
                }

                try {
                    $response = Http::timeout(30)->get($imageUrl);

                    if (! $response->successful()) {
                        $this->warn("  Failed ({$response->status()}): {$basename}");
                        $localImages[] = $imageUrl; // Keep CDN URL as fallback
                        $failed++;

                        continue;
                    }

                    Storage::disk('public')->put($localPath, $response->body());
                    $localImages[] = $localPath;
                    $downloaded++;
                    $changed = true;
                } catch (\Exception $e) {
                    $this->warn("  Error downloading {$basename}: {$e->getMessage()}");
                    $localImages[] = $imageUrl;
                    $failed++;
                }

                // Be polite
                usleep(100000);
            }

            if ($changed && ! $this->option('dry-run')) {
                $blueprint->update([
                    'images' => $localImages,
                    'image' => $localImages[0] ?? null,
                ]);
            }

            $this->line(sprintf(
                '  %s: %d images',
                $blueprint->name,
                count($localImages),
            ));
        }

        $this->newLine();
        $this->info(sprintf('Done! Downloaded: %d, Skipped: %d, Failed: %d', $downloaded, $skipped, $failed));

        return self::SUCCESS;
    }

    /**
     * Sanitize a CDN filename: decode URL encoding, replace spaces/plus with hyphens.
     */
    private function sanitizeFilename(string $filename): string
    {
        $decoded = urldecode($filename);
        // Replace spaces and plus signs with hyphens
        $cleaned = (string) preg_replace('/[\s+]+/', '-', $decoded);
        // Remove parentheses and other problematic chars
        $cleaned = (string) preg_replace('/[()]+/', '', $cleaned);
        // Collapse multiple hyphens
        $cleaned = (string) preg_replace('/-{2,}/', '-', $cleaned);

        return trim($cleaned, '-');
    }
}
