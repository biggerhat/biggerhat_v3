<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up the WeeklyHireController's "have I hired this week already?"
 * check (`acquired_via = 'hire' AND acquired_week = ?` filtered by crew). The
 * (campaign_crew_id, annihilated_at) index from the initial migration doesn't
 * cover this query in MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->index(['campaign_crew_id', 'acquired_week'], 'cam_crew_week_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropIndex('cam_crew_week_idx');
        });
    }
};
