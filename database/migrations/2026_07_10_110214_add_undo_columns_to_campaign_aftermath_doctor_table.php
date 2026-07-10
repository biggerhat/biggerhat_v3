<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets "go back a phase" reverse a Doctor (Phase 5) attempt. Today
 * `target_injury_id` is stored as null for any outcome that removes the
 * original injury (see make_doctor_log_target_injury_nullable) — the
 * original injury_upgrade_id is genuinely lost, so a `removed` /
 * `gained_undead` / `gained_construct` / `lucky_miss_reflip` outcome can't
 * be restored. These columns capture what's needed to undo each outcome:
 *
 *   - removed_injury_upgrade_id: the injury that was removed, so it can be
 *     re-inserted verbatim.
 *   - added_injury_pivot_id: the newly-inserted pivot for `added_injury` /
 *     `removed_and_reflip`, so it can be deleted on undo.
 *   - created_copy_model_id: the model `copyForCampaign()` spawned for a
 *     Doppelganger result, so it can be deleted on undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->foreignId('removed_injury_upgrade_id')->nullable()->after('target_injury_id')
                ->constrained('upgrades')->nullOnDelete();
            $table->foreignId('added_injury_pivot_id')->nullable()->after('removed_injury_upgrade_id')
                ->constrained('campaign_arsenal_model_injuries')->nullOnDelete();
            $table->foreignId('created_copy_model_id')->nullable()->after('added_injury_pivot_id')
                ->constrained('campaign_arsenal_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('removed_injury_upgrade_id');
            $table->dropConstrainedForeignId('added_injury_pivot_id');
            $table->dropConstrainedForeignId('created_copy_model_id');
        });
    }
};
