<?php

namespace App\Console\Commands;

use App\Models\BattleReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes battle report images no report uses any more: uploaded in the editor and then removed from the text, or left behind
 * by a report that was deleted. Files younger than a day are kept, since a report being written has not been saved yet.
 */
class PruneBattleReportImages extends Command
{
    protected $signature = 'battle-reports:prune-images {--dry-run : List what would go without deleting}';

    protected $description = 'Delete uploaded battle report images that no report refers to';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $used = [];
        // Every report's stored text, in pieces so a large table does not sit in memory at once.
        BattleReport::query()->select(['id', 'content'])->chunkById(200, function ($reports) use (&$used) {
            foreach ($reports as $report) {
                if (preg_match_all('#/storage/(battle-reports/[A-Za-z0-9/_\-.]+)#', (string) json_encode($report->content, JSON_UNESCAPED_SLASHES), $m)) {
                    foreach ($m[1] as $path) {
                        $used[$path] = true;
                    }
                }
            }
        });

        $cutoff = now()->subDay()->getTimestamp();
        $removed = 0;
        foreach ($disk->allFiles('battle-reports') as $path) {
            if (isset($used[$path]) || $disk->lastModified($path) > $cutoff) {
                continue;
            }
            $this->line(($this->option('dry-run') ? 'Would delete ' : 'Deleting ').$path);
            if (! $this->option('dry-run')) {
                $disk->delete($path);
            }
            $removed++;
        }
        $this->info(($this->option('dry-run') ? 'Unused images: ' : 'Deleted: ').$removed);

        return self::SUCCESS;
    }
}
