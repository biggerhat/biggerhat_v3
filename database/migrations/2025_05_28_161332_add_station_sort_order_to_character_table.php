<?php

use App\Models\Character;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->after('station', function (Blueprint $table) {
                $table->integer('station_sort_order')->default(0);
            });
        });

        $characters = Character::all();
        $characters->each(function (Character $character) {
            $character->update([
                'station_sort_order' => $character->station ? $character->station->sortOrder() : \App\Enums\CharacterStationEnum::NON_STATION_SORT_ORDER,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('station_sort_order');
        });
    }
};
