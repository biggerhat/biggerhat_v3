<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attack Mod / Tactical Mod advancements (pg 38-43) can target an action
 * granted by a piece of Equipment rather than the Leader/Totem's own
 * actions[] array (pg 31: "if the action is from a piece of equipment, the
 * leader must always take that equipment if possible going forward").
 * Equipment's actions come from the shared `actions` catalog table via its
 * Upgrade record, not a per-instance JSON array, so the target action needs
 * a real FK here rather than an array index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->foreignId('applied_to_action_id')->nullable()
                ->after('applied_to_action_index')
                ->constrained('actions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->dropForeign(['applied_to_action_id']);
            $table->dropColumn('applied_to_action_id');
        });
    }
};
