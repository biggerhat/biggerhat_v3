<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixBlueprintFilenames extends Command
{
    protected $signature = 'app:fix-blueprint-filenames {--dry-run : Show changes without applying}';

    protected $description = 'Rename blueprint image files with problematic characters (+, spaces, encoded chars)';

    public function handle(): int
    {
        $fixed = 0;
        $dryRun = $this->option('dry-run');

        Blueprint::withImages()->chunk(100, function ($blueprints) use (&$fixed, $dryRun) {
            foreach ($blueprints as $blueprint) {
                $images = $blueprint->images ?? [];
                $newImages = [];
                $changed = false;

                foreach ($images as $path) {
                    $dir = dirname($path);
                    $oldBasename = basename($path);
                    $newBasename = $this->sanitize($oldBasename);

                    if ($oldBasename === $newBasename) {
                        $newImages[] = $path;

                        continue;
                    }

                    $newPath = "{$dir}/{$newBasename}";
                    $this->line("  {$path} → {$newPath}");

                    if (! $dryRun && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->move($path, $newPath);
                    }

                    $newImages[] = $newPath;
                    $changed = true;
                }

                if ($changed) {
                    $fixed++;
                    if (! $dryRun) {
                        $blueprint->update([
                            'images' => $newImages,
                            'image' => $newImages[0] ?? null,
                        ]);
                    }
                }
            }
        });

        $this->info(sprintf('%s %d blueprints.', $dryRun ? 'Would fix' : 'Fixed', $fixed));

        return self::SUCCESS;
    }

    private function sanitize(string $filename): string
    {
        $decoded = urldecode($filename);
        $cleaned = (string) preg_replace('/[\s+]+/', '-', $decoded);
        $cleaned = (string) preg_replace('/[()]+/', '', $cleaned);
        $cleaned = (string) preg_replace('/-{2,}/', '-', $cleaned);

        return trim($cleaned, '-');
    }
}
