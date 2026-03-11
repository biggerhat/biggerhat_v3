<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;

class CleanBlueprintImages extends Command
{
    protected $signature = 'app:clean-blueprint-images {--dry-run : Show changes without saving}';

    protected $description = 'Remove non-blueprint images (favicons, site chrome) from existing blueprint records';

    private const JUNK_BASENAMES = [
        'favicon.ico',
        'image+63.png',
        'image+13.png',
    ];

    public function handle(): int
    {
        $updated = 0;
        $dryRun = $this->option('dry-run');

        Blueprint::chunk(100, function ($blueprints) use (&$updated, $dryRun) {
            foreach ($blueprints as $blueprint) {
                $original = $blueprint->images ?? [];
                $filtered = array_values(array_filter($original, function (string $url) {
                    $basename = basename($url);

                    if (in_array($basename, self::JUNK_BASENAMES, true)) {
                        return false;
                    }

                    if (str_starts_with($basename, 'image-asset.')) {
                        return false;
                    }

                    return true;
                }));

                $removed = count($original) - count($filtered);
                if ($removed === 0) {
                    continue;
                }

                $this->line(sprintf(
                    '  %s: %d → %d images (-%d)',
                    $blueprint->name,
                    count($original),
                    count($filtered),
                    $removed,
                ));

                if (! $dryRun) {
                    $blueprint->update([
                        'images' => $filtered,
                        'image' => $filtered[0] ?? null,
                    ]);
                }

                $updated++;
            }
        });

        $this->info(sprintf('%s %d blueprints.', $dryRun ? 'Would update' : 'Updated', $updated));

        return self::SUCCESS;
    }
}
