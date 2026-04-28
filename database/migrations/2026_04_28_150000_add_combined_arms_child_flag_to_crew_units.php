<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Combined Arms parent units pull in their child unit automatically when
 * hired (rulebook p. 11). Storing the auto-attached row as a regular
 * `tos_crew_units` entry keeps the per-unit asset attachments and position
 * ordering uniform, and the boolean flag lets the controller block
 * standalone removal — the child can only leave the Crew when its parent
 * does, mirroring the rulebook pairing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_crew_units', function (Blueprint $table) {
            $table->boolean('is_combined_arms_child')->default(false)->after('is_commander');
        });
    }

    public function down(): void
    {
        Schema::table('tos_crew_units', function (Blueprint $table) {
            $table->dropColumn('is_combined_arms_child');
        });
    }
};
