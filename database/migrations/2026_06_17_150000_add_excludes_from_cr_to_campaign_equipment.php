<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campaign Rating counts a crew's pieces of equipment (pg 19), but several
     * acquisition routes explicitly never count: the Lucky Upstart / leader-build
     * "Special" free equipment and Loot Their Stash both state the equipment
     * "never counts towards your campaign rating." That exclusion is tied to how
     * the instance was acquired — the same catalog item DOES count when bought at
     * Barter — so the flag lives per-instance, not on the upgrade catalog row.
     */
    public function up(): void
    {
        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->boolean('excludes_from_cr')->default(false)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->dropColumn('excludes_from_cr');
        });
    }
};
