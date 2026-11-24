<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turns every existing collection row into its physical models: a row owned 3 times and marked painted becomes three painted
     * models, built becomes three built ones, anything else three unbuilt. Nothing is removed from `user_miniatures`. Safe to run
     * twice: a miniature that already has models is skipped.
     */
    public function up(): void
    {
        DB::table('user_miniatures')->orderBy('id')->chunkById(500, function ($rows) {
            $have = DB::table('collection_models')
                ->whereIn('user_id', $rows->pluck('user_id')->unique())
                ->select('user_id', 'miniature_id')
                ->groupBy('user_id', 'miniature_id')
                ->get()
                ->mapWithKeys(fn ($r) => ["{$r->user_id}:{$r->miniature_id}" => true]);

            $insert = [];
            foreach ($rows as $row) {
                if (isset($have["{$row->user_id}:{$row->miniature_id}"])) {
                    continue;
                }
                $stage = ! empty($row->is_painted) ? 'painted' : (! empty($row->is_built) ? 'built' : 'unbuilt');
                for ($i = 0; $i < max(1, (int) $row->quantity); $i++) {
                    $insert[] = [
                        'user_id' => $row->user_id,
                        'miniature_id' => $row->miniature_id,
                        'stage' => $stage,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => now(),
                    ];
                }
            }
            foreach (array_chunk($insert, 500) as $part) {
                DB::table('collection_models')->insert($part);
            }
        });
    }

    /** The models are rebuilt from the collection rows if this is run again; rolling back the table drops them. */
    public function down(): void {}
};
