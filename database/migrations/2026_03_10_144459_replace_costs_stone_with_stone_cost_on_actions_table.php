<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->unsignedTinyInteger('stone_cost')->default(0)->after('is_signature');
        });

        // Migrate existing boolean data: true → 1, false → 0
        DB::table('actions')->where('costs_stone', true)->update(['stone_cost' => 1]);

        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn('costs_stone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->boolean('costs_stone')->default(false)->after('is_signature');
        });

        DB::table('actions')->where('stone_cost', '>', 0)->update(['costs_stone' => true]);

        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn('stone_cost');
        });
    }
};
