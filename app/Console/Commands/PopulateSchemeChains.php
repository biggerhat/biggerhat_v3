<?php

namespace App\Console\Commands;

use App\Models\Scheme;
use Illuminate\Console\Command;

class PopulateSchemeChains extends Command
{
    protected $signature = 'schemes:populate-chains';

    protected $description = 'Populate next_scheme fields with random schemes from the same season';

    public function handle(): int
    {
        $seasons = Scheme::select('season')->distinct()->pluck('season');
        $updated = 0;

        foreach ($seasons as $season) {
            $schemes = Scheme::where('season', $season)->get();
            if ($schemes->count() < 4) {
                $this->warn("Season {$season->value} has fewer than 4 schemes, skipping.");

                continue;
            }

            foreach ($schemes as $scheme) {
                $others = $schemes->where('id', '!=', $scheme->id)->shuffle()->take(3)->values();
                $scheme->update([
                    'next_scheme_one_id' => $others[0]->id,
                    'next_scheme_two_id' => $others[1]->id,
                    'next_scheme_three_id' => $others[2]->id,
                ]);
                $updated++;
            }

            $this->info("Season {$season->value}: populated {$schemes->count()} schemes.");
        }

        $this->info("Done. Updated {$updated} schemes with random next-scheme chains.");

        return self::SUCCESS;
    }
}
