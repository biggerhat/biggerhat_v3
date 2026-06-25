<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Back-Alley Doctor log records each attempt's targeted injury, but a
     * "removed" outcome deletes that injury pivot before the log row is written
     * — inserting the now-dangling id blew up the FK in MySQL (500). It is an
     * audit row, so the reference must be nullable + nullOnDelete (survive the
     * injury removal) and the controller stores null when the injury is gone.
     */
    public function up(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropForeign(['target_injury_id']);
        });

        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedBigInteger('target_injury_id')->nullable()->change();
            $table->foreign('target_injury_id')
                ->references('id')->on('campaign_arsenal_model_injuries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->dropForeignSafe('campaign_aftermath_doctor', ['target_injury_id']);
        });

        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedBigInteger('target_injury_id')->nullable(false)->change();
            $table->foreign('target_injury_id')
                ->references('id')->on('campaign_arsenal_model_injuries')
                ->cascadeOnDelete();
        });
    }
};
