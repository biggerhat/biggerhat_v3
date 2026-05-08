<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-game format flag — Standard (default Malifaux encounter) vs custom
     * formats like Bonanza Brawl. Stored as a string-backed enum so adding
     * future formats (Henchman Hardcore, etc.) is just a new enum case.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('format')->default('standard')->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
