<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the 2026-04-28 `tos_allegiances.secondary_type` migration. Hybrid
 * Allegiance Cards print BOTH Earth and Malifaux on the card and need to
 * carry both type-derived applicability pools at once. Single-type cards
 * leave the column null and behave exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_allegiance_cards', function (Blueprint $table) {
            $table->string('secondary_type', 16)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tos_allegiance_cards', function (Blueprint $table) {
            $table->dropColumn('secondary_type');
        });
    }
};
