<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->timestamp('reported_at')->index();
            $table->string('bot_version', 32)->nullable();
            $table->unsignedInteger('interval_seconds');
            $table->unsignedBigInteger('uptime_seconds');
            $table->integer('ws_ping_ms')->nullable();
            $table->unsignedInteger('guild_count');
            $table->unsignedInteger('memory_mb')->nullable();
            // Counters below cover this reporting interval only (deltas), so
            // charts and totals are plain sums over rows.
            $table->unsignedInteger('slash_count')->default(0);
            $table->unsignedInteger('prefix_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('api_calls')->default(0);
            $table->unsignedInteger('api_failures')->default(0);
            $table->unsignedInteger('api_retries')->default(0);
            $table->unsignedInteger('api_latency_ms_avg')->nullable();
            $table->unsignedInteger('cache_hits')->default(0);
            $table->unsignedInteger('cache_misses')->default(0);
            $table->json('commands')->nullable();
            $table->json('error_samples')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_heartbeats');
    }
};
