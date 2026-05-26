<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solo Campaign Mode — a single user tracks their own crew without needing
 * other players. Two schema tweaks:
 *
 *   1. `campaigns.is_solo` boolean — set at create time, immutable after,
 *      gates the 2-player Start check and the invitation flow.
 *   2. `campaign_games.crew_b_id` made nullable — solo games are logged
 *      manually with VP / schemes / win flag; no opponent crew exists.
 *      `base_game_id` is already nullable so the live tracker is opt-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('is_solo')->default(false)->after('weekly_event_active');
        });

        Schema::table('campaign_games', function (Blueprint $table) {
            $table->dropForeign(['crew_b_id']);
            $table->foreignId('crew_b_id')->nullable()->change()
                ->constrained('campaign_crews')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_games', function (Blueprint $table) {
            $table->dropForeign(['crew_b_id']);
            // Best-effort restore: cascadeOnDelete is the original behavior,
            // but we can't reliably re-NOT-NULL the column if any solo rows
            // exist. Caller is expected to clean those up before rolling back.
            $table->foreignId('crew_b_id')->change()
                ->constrained('campaign_crews')->cascadeOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('is_solo');
        });
    }
};
