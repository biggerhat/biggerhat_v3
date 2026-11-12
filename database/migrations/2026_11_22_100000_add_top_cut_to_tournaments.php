<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Size of the single-elimination top cut played after the Swiss rounds (null = no cut).
            $table->unsignedTinyInteger('cut_size')->nullable();
        });

        Schema::table('tournament_rounds', function (Blueprint $table) {
            // Elimination rounds are ordinary rounds flagged as cut rounds: their games stay out of the Swiss standings.
            $table->boolean('is_cut')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rounds', function (Blueprint $table) {
            $table->dropColumn('is_cut');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('cut_size');
        });
    }
};
