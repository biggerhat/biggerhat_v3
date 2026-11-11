<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // When on, linked players can submit their own game's result for the opponent to confirm.
            $table->boolean('allow_self_report')->default(false);
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            // A player-submitted result awaiting the opponent's confirmation (or disputed). Never counts
            // towards standings until confirmed or entered by the organizer.
            $table->string('report_status', 16)->nullable();
            $table->foreignId('reported_by_player_id')->nullable()->constrained('tournament_players', 'id', 'tg_reported_by_fk')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->json('reported_score')->nullable();
            $table->string('report_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropForeign('tg_reported_by_fk');
            $table->dropColumn(['report_status', 'reported_by_player_id', 'reported_at', 'reported_score', 'report_note']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('allow_self_report');
        });
    }
};
