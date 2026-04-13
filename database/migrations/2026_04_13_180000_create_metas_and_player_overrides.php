<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a "meta" (gaming community / scene / regional group) concept.
 *
 * - `metas` table holds canonical meta names. New entries are created on
 *   the fly when a user or TO types a name that doesn't exist yet, so we
 *   keep the table small and self-curating.
 * - `users.meta_id` lets a player permanently associate with their home
 *   meta from their profile.
 * - `tournament_players.meta_id` lets the TO override per-tournament for
 *   non-account players (or for an account-linked player who's playing
 *   "as a guest" of another meta).
 *
 * The Round 1 pairing algorithm uses these to avoid pairing two
 * same-meta players unless mathematically forced.
 *
 * Also marks games as manually paired so the auto-pair button can fill
 * in just the unpaired remainder rather than wiping a TO's manual work.
 *
 * Also adds configurable bye scoring (defaults match the previous
 * hard-coded Gaining Grounds values: 3 TP / +4 DIFF / 6 VP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('meta_id')->nullable()->after('id')->constrained('metas')->nullOnDelete();
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->foreignId('meta_id')->nullable()->after('user_id')->constrained('metas')->nullOnDelete();
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            // Manually-created pairings survive a re-pair / partial auto-pair.
            $table->boolean('is_manual')->default(false)->after('is_bye');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            // Configurable bye scoring — defaults preserve current Gaining Grounds values.
            $table->unsignedTinyInteger('bye_tp')->default(3)->after('round_time_limit');
            $table->unsignedTinyInteger('bye_diff')->default(4)->after('bye_tp');
            $table->unsignedTinyInteger('bye_vp')->default(6)->after('bye_diff');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['bye_tp', 'bye_diff', 'bye_vp']);
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });

        Schema::table('tournament_players', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meta_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meta_id');
        });

        Schema::dropIfExists('metas');
    }
};
