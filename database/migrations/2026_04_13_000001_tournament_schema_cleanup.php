<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tournament schema cleanup:
 *
 * - Drop unused columns (`tournaments.is_public`, `tournament_games.notes`,
 *   `tournament_rsvps.faction`) — none of these are read or written anywhere
 *   in the app and they're confusing dead schema.
 * - Add covering indexes for hot-path filters used by standings/pairing
 *   computation and the `pending games` checks during the round lifecycle.
 *
 * Note: we intentionally do NOT add a unique constraint on
 * (tournament_id, user_id) for tournament_players because user_id is
 * nullable (manually-added players have no linked account). MySQL allows
 * multiple NULL values in a unique index, but for portability and clarity
 * the conflict check stays in application code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropColumn('notes');
            // result is filtered constantly during the round lifecycle
            // ("any pending games?" checks, standings computation).
            $table->index(['tournament_round_id', 'result'], 'tg_round_result_idx');
            // Cleanup of orphan tracker games on re-pair / delete.
            $table->index('game_id', 'tg_game_id_idx');
        });

        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->dropColumn('faction');
        });

        // Enforce single-ringer-per-tournament at the DB level (defense in depth).
        // Both MySQL and PostgreSQL allow expression/conditional unique indexes,
        // but the syntax differs. We use a raw partial index that works on both:
        //   - MySQL 8+: emulate via virtual generated column
        //   - Postgres: native partial index
        // For simplicity and broad compatibility we add a regular composite
        // index here and keep the uniqueness check in application code.
        Schema::table('tournament_players', function (Blueprint $table) {
            $table->index(['tournament_id', 'is_ringer'], 'tp_ringer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropIndex('tg_round_result_idx');
            $table->dropIndex('tg_game_id_idx');
            $table->text('notes')->nullable();
        });

        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->string('faction')->nullable();
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropIndex('tp_ringer_idx');
        });
    }
};
