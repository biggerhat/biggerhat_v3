<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links Lore entries to TOS Units — the TOS analog of `character_lore`. A Lore
 * piece can reference many Units and a Unit can appear in many Lore entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lore_tos_unit', function (Blueprint $table) {
            $table->foreignId('lore_id')->constrained('lores')->cascadeOnDelete();
            $table->foreignId('tos_unit_id')->constrained('tos_units')->cascadeOnDelete();
            $table->primary(['lore_id', 'tos_unit_id'], 'lore_tos_unit_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lore_tos_unit');
    }
};
