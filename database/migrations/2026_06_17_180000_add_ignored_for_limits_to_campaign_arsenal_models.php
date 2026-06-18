<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Doppelganger copy (Lucky Miss any-joker, pg 36) is added to the arsenal but
 * "ignored when determining model limits for hiring, summoning, etc." Flag those
 * copies so the hiring/limit logic can exclude them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->boolean('ignored_for_limits')->default(false)->after('is_peon');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropColumn('ignored_for_limits');
        });
    }
};
