<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lore_lore_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lore_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lore_media_id')->constrained('lore_media')->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing foreign key data to pivot table
        DB::table('lores')->whereNotNull('lore_media_id')->orderBy('id')->each(function ($lore) {
            DB::table('lore_lore_media')->insert([
                'lore_id' => $lore->id,
                'lore_media_id' => $lore->lore_media_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('lores', function (Blueprint $table) {
            $table->dropForeign(['lore_media_id']);
            $table->dropColumn('lore_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('lores', function (Blueprint $table) {
            $table->foreignId('lore_media_id')->nullable()->constrained('lore_media')->cascadeOnDelete();
        });

        // Migrate back: take first media for each lore
        DB::table('lore_lore_media')->orderBy('id')->each(function ($pivot) {
            DB::table('lores')->where('id', $pivot->lore_id)->update([
                'lore_media_id' => $pivot->lore_media_id,
            ]);
        });

        Schema::dropIfExists('lore_lore_media');
    }
};
