<?php

namespace App\Console\Commands;

use App\Models\Miniature;
use App\Observers\MiniatureObserver;
use Illuminate\Console\Command;

class RefreshMiniatureSculptNames extends Command
{
    protected $signature = 'miniatures:refresh-sculpt-names';

    protected $description = 'Recalculate (Sculpt N) suffixes on miniature display_names for characters with multiple sculpts';

    public function handle(): int
    {
        $characterIds = Miniature::distinct()->pluck('character_id');

        $bar = $this->output->createProgressBar($characterIds->count());
        $bar->start();

        $updated = 0;

        foreach ($characterIds as $characterId) {
            $before = Miniature::where('character_id', $characterId)->pluck('display_name', 'id');

            MiniatureObserver::refreshSculptSuffixes($characterId);

            $after = Miniature::where('character_id', $characterId)->pluck('display_name', 'id');

            foreach ($after as $id => $name) {
                if (($before[$id] ?? null) !== $name) {
                    $this->line(" {$before[$id]} → {$name}");
                    $updated++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Updated {$updated} miniature display names.");

        return self::SUCCESS;
    }
}
