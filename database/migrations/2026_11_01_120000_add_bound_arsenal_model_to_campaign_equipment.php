<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrupted Pawns (optional rule, pg 146): a starting Those Who Thirst item is permanently
 * attached to one arsenal model. It can't be moved, discarded or stolen, and is annihilated
 * with that model. Everything else in `campaign_equipment` stays a crew-level pool, so this
 * nullable pointer is the only per-model link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('bound_arsenal_model_id')->nullable()->after('acquired_aftermath_id');
            $table->foreign('bound_arsenal_model_id', 'fk_ce_bound_arsenal_model')
                ->references('id')->on('campaign_arsenal_models')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_equipment', function (Blueprint $table) {
            $table->dropForeignSafe('fk_ce_bound_arsenal_model');
            $table->dropColumn('bound_arsenal_model_id');
        });
    }
};
