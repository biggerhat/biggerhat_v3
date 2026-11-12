<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_series', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Points for a finishing place: index 0 = 1st, 1 = 2nd, ... Anyone placed lower (but who played) earns the participation points.
            $table->json('points_table');
            $table->unsignedSmallInteger('participation_points')->default(1);
            // Only a player's best N events count (null = all of them).
            $table->unsignedTinyInteger('best_of')->nullable();
            $table->timestamps();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->constrained('tournament_series')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('series_id');
        });

        Schema::dropIfExists('tournament_series');
    }
};
