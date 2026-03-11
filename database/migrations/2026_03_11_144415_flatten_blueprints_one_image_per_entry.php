<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flatten blueprints so each row = 1 image.
     * Copies morph pivot relationships from the parent blueprint to each new child row.
     */
    public function up(): void
    {
        // 1. Add image_path column
        Schema::table('blueprints', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('wyrd_post_slug');
        });

        // 2. Drop unique constraint on wyrd_post_slug (multiple rows per post now)
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropUnique('blueprints_wyrd_post_slug_unique');
            $table->index('wyrd_post_slug');
        });

        // 3. Migrate data: expand each blueprint into N rows (one per image)
        if (Schema::hasTable('blueprint_images')) {
            $blueprints = DB::table('blueprints')->get();

            foreach ($blueprints as $blueprint) {
                $images = DB::table('blueprint_images')
                    ->where('blueprint_id', $blueprint->id)
                    ->orderBy('sort_order')
                    ->get();

                if ($images->isEmpty()) {
                    // Blueprint with no images — leave as-is (image_path stays null)
                    continue;
                }

                // First image stays on the original row
                DB::table('blueprints')
                    ->where('id', $blueprint->id)
                    ->update(['image_path' => $images->first()->path]);

                // Remaining images become new blueprint rows
                $pivotTables = ['characterables', 'miniatureables', 'packageables'];
                $existingPivots = [];
                foreach ($pivotTables as $pivotTable) {
                    $typeColumn = str_replace('ables', 'able_type', $pivotTable);
                    $idColumn = str_replace('ables', 'able_id', $pivotTable);
                    $fkColumn = str_replace('ables', '_id', str_replace('ables', '', $pivotTable));
                    // e.g. characterables -> characterable_type, characterable_id, character_id
                    $singularFk = rtrim($pivotTable, 's').'_id';
                    // Actually let's just query properly
                    $existingPivots[$pivotTable] = DB::table($pivotTable)
                        ->where($typeColumn, 'App\\Models\\Blueprint')
                        ->where($idColumn, $blueprint->id)
                        ->get();
                }

                foreach ($images->skip(1) as $image) {
                    $newId = DB::table('blueprints')->insertGetId([
                        'name' => $blueprint->name,
                        'slug' => $blueprint->slug,
                        'image_path' => $image->path,
                        'source_url' => $blueprint->source_url,
                        'wyrd_post_slug' => $blueprint->wyrd_post_slug,
                        'sculpt_version' => $blueprint->sculpt_version,
                        'published_at' => $blueprint->published_at,
                        'created_at' => $blueprint->created_at,
                        'updated_at' => now(),
                    ]);

                    // Copy pivot relationships to new row
                    foreach ($pivotTables as $pivotTable) {
                        $typeColumn = str_replace('ables', 'able_type', $pivotTable);
                        $idColumn = str_replace('ables', 'able_id', $pivotTable);

                        foreach ($existingPivots[$pivotTable] as $pivot) {
                            $row = (array) $pivot;
                            unset($row['id']);
                            $row[$idColumn] = $newId;
                            DB::table($pivotTable)->insert($row);
                        }
                    }
                }
            }

            // 4. Drop blueprint_images table
            Schema::dropIfExists('blueprint_images');
        }
    }

    public function down(): void
    {
        // Recreate blueprint_images table
        Schema::create('blueprint_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Migrate image_path back to blueprint_images for each row
        $blueprints = DB::table('blueprints')->whereNotNull('image_path')->get();
        foreach ($blueprints as $blueprint) {
            DB::table('blueprint_images')->insert([
                'blueprint_id' => $blueprint->id,
                'path' => $blueprint->image_path,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropIndex(['wyrd_post_slug']);
            $table->dropColumn('image_path');
        });

        // Note: unique constraint on wyrd_post_slug cannot be restored
        // because multiple rows may share the same slug after flattening
    }
};
