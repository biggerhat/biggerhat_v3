<?php

use App\Models\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint as SchemaBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprint_images', function (SchemaBlueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained('blueprints')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('blueprint_id');
        });

        // Migrate existing images JSON into blueprint_images rows
        Blueprint::withTrashed()->whereNotNull('images')->get()->each(function (Blueprint $blueprint) {
            $images = is_string($blueprint->getRawOriginal('images'))
                ? json_decode($blueprint->getRawOriginal('images'), true)
                : $blueprint->images;

            if (! is_array($images) || empty($images)) {
                return;
            }

            $rows = [];
            foreach (array_values($images) as $i => $path) {
                $rows[] = [
                    'blueprint_id' => $blueprint->id,
                    'path' => $path,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('blueprint_images')->insert($rows);
        });

        Schema::table('blueprints', function (SchemaBlueprint $table) {
            $table->dropColumn(['image', 'images']);
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (SchemaBlueprint $table) {
            $table->string('image')->nullable()->after('slug');
            $table->json('images')->nullable()->after('image');
        });

        $grouped = DB::table('blueprint_images')->orderBy('blueprint_id')->orderBy('sort_order')->get()->groupBy('blueprint_id');
        foreach ($grouped as $blueprintId => $images) {
            $paths = $images->pluck('path')->toArray();
            DB::table('blueprints')->where('id', $blueprintId)->update([
                'image' => $paths[0] ?? null,
                'images' => json_encode($paths),
            ]);
        }

        Schema::dropIfExists('blueprint_images');
    }
};
