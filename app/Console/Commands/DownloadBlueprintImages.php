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
        $blueprints = Blueprint::withImage()->whereRaw("image_path LIKE 'http%'")->get();
        $this->info("Processing {$blueprints->count()} blueprints with CDN images...");

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($blueprints as $blueprint) {
            $basename = $this->sanitizeFilename(basename(parse_url($blueprint->image_path, PHP_URL_PATH) ?: ''));
            if (! $basename) {
                $failed++;

                continue;
            }

            $localPath = "blueprints/{$blueprint->id}/{$basename}";

            if (! $this->option('force') && Storage::disk('public')->exists($localPath)) {
                $blueprint->update(['image_path' => $localPath]);
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  <info>DOWNLOAD</info> {$basename} → {$localPath}");
                $downloaded++;

                continue;
            }

            try {
                $response = Http::timeout(30)->get($blueprint->image_path);

                if (! $response->successful()) {
                    $this->warn("  Failed ({$response->status()}): {$basename}");
                    $failed++;

                    continue;
                }

                Storage::disk('public')->put($localPath, $response->body());
                $blueprint->update(['image_path' => $localPath]);
                $downloaded++;
            } catch (\Exception $e) {
                $this->warn("  Error downloading {$basename}: {$e->getMessage()}");
                $failed++;
            }

            usleep(100000);
        }

        $this->newLine();
        $this->info(sprintf('Done! Downloaded: %d, Skipped: %d, Failed: %d', $downloaded, $skipped, $failed));

        return self::SUCCESS;
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
