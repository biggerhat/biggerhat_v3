<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;

class CleanBlueprintImages extends Command
{
    protected $signature = 'app:clean-blueprint-images {--dry-run : Show changes without saving}';

    protected $description = 'Remove blueprint entries with junk images (favicons, site chrome)';

    private const JUNK_BASENAMES = [
        'favicon.ico',
        'image+63.png',
        'image+13.png',
    ];

    public function handle(): int
    {
        $deleted = 0;
        $dryRun = $this->option('dry-run');

        Blueprint::withImage()->chunk(200, function ($blueprints) use (&$deleted, $dryRun) {
            foreach ($blueprints as $blueprint) {
                $basename = basename($blueprint->image_path);

                if (! in_array($basename, self::JUNK_BASENAMES, true)) {
                    continue;
                }

                $this->line("  [{$blueprint->name}] Removing: {$basename}");

                if (! $dryRun) {
                    $blueprint->characters()->detach();
                    $blueprint->miniatures()->detach();
                    $blueprint->packages()->detach();
                    $blueprint->forceDelete();
                }

                $deleted++;
            }
        });

        $this->info(sprintf('%s %d junk blueprints.', $dryRun ? 'Would remove' : 'Removed', $deleted));

        return self::SUCCESS;
    }
}
