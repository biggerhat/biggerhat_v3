<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-model progress for Squad units (rulebook Special Unit Rule, e.g.
 * "Squad of 9" — a box of individually-paintable miniatures). The existing
 * `is_built`/`is_painted` booleans stay authoritative for everything else;
 * these counts only matter for a sculpt whose Unit carries the Squad rule,
 * where they track how many of that box's models are built/painted
 * (0..fireteam_count), independent of `quantity` (how many boxes owned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_unit_sculpts', function (Blueprint $table) {
            $table->unsignedSmallInteger('built_count')->default(0)->after('is_built');
            $table->unsignedSmallInteger('painted_count')->default(0)->after('is_painted');
        });
    }

    public function down(): void
    {
        Schema::table('user_unit_sculpts', function (Blueprint $table) {
            $table->dropColumn(['built_count', 'painted_count']);
        });
    }
};
