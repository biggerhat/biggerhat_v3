<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skl Boost rows (pg 38-43) can qualify on a range of Skl values ("select one
 * attack with a Skl of 0 or 1"), not just a single exact value — skl_from is
 * the range minimum, skl_from_max the (optional) maximum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advancement_attack_mods', function (Blueprint $table) {
            $table->unsignedTinyInteger('skl_from_max')->nullable()->after('skl_from');
        });
        Schema::table('advancement_tactical_mods', function (Blueprint $table) {
            $table->unsignedTinyInteger('skl_from_max')->nullable()->after('skl_from');
        });
    }

    public function down(): void
    {
        Schema::table('advancement_attack_mods', function (Blueprint $table) {
            $table->dropColumn('skl_from_max');
        });
        Schema::table('advancement_tactical_mods', function (Blueprint $table) {
            $table->dropColumn('skl_from_max');
        });
    }
};
