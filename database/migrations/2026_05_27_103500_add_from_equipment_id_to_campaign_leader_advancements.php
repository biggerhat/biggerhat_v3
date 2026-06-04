<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment-from-action advancement linkage (pg 31 / rulebook §12).
 *
 * When an Attack/Tactical Modification Advancement is applied to an action
 * that came from a piece of Equipment, the rule requires that the leader
 * "must always take that equipment if possible going forward". If the
 * equipment is later annihilated, the advancement is permanently lost.
 *
 * We track the originating equipment row so the renderer can:
 *   1. Refuse to apply the advancement when the equipment is annihilated.
 *   2. Force the equipment to be carried when the advancement is in effect.
 *
 * Nullable — only set on advancements whose target action is equipment-derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->unsignedBigInteger('from_equipment_id')->nullable();
            $table->index('from_equipment_id', 'idx_cla_from_equipment');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->dropIndex('idx_cla_from_equipment');
            $table->dropColumn('from_equipment_id');
        });
    }
};
