<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tournament tiebreaker mode for standings ordering:
 *   - 'diff_vp' (default, preserves current behavior): TP → DIFF → VP
 *   - 'sos':                                            TP → SoS → DIFF → VP
 *
 * SoS = sum of every opponent's tournament points (Buchholz / Solkoff),
 * excluding byes. Always computed and shown on standings; only sorting
 * changes between the two modes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('tiebreaker_mode', 16)->default('diff_vp')->after('bye_vp');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('tiebreaker_mode');
        });
    }
};
