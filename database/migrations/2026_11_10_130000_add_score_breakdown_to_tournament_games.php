<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-player score components (e.g. Malifaux strategy/scheme VP) move from fixed columns
 * into a ruleset-described JSON blob: {"player_one": {"strategy": 3, "scheme": 2}, "player_two": {...}}.
 * The totals stay in player_one_vp / player_two_vp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_games', function (Blueprint $table) {
            $table->json('score_breakdown')->nullable()->after('player_two_vp');
        });

        DB::table('tournament_games')
            ->where(fn ($q) => $q->whereNotNull('player_one_strategy_vp')->orWhereNotNull('player_one_scheme_vp')
                ->orWhereNotNull('player_two_strategy_vp')->orWhereNotNull('player_two_scheme_vp'))
            ->orderBy('id')
            ->chunkById(500, function ($games) {
                foreach ($games as $g) {
                    DB::table('tournament_games')->where('id', $g->id)->update(['score_breakdown' => json_encode([
                        'player_one' => ['strategy' => (int) $g->player_one_strategy_vp, 'scheme' => (int) $g->player_one_scheme_vp],
                        'player_two' => ['strategy' => (int) $g->player_two_strategy_vp, 'scheme' => (int) $g->player_two_scheme_vp],
                    ])]);
                }
            });

        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropColumn(['player_one_strategy_vp', 'player_one_scheme_vp', 'player_two_strategy_vp', 'player_two_scheme_vp']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_games', function (Blueprint $table) {
            $table->unsignedTinyInteger('player_one_strategy_vp')->nullable()->after('player_one_vp');
            $table->unsignedTinyInteger('player_one_scheme_vp')->nullable()->after('player_one_strategy_vp');
            $table->unsignedTinyInteger('player_two_strategy_vp')->nullable()->after('player_two_vp');
            $table->unsignedTinyInteger('player_two_scheme_vp')->nullable()->after('player_two_strategy_vp');
        });

        DB::table('tournament_games')->whereNotNull('score_breakdown')->orderBy('id')->chunkById(500, function ($games) {
            foreach ($games as $g) {
                $b = json_decode((string) $g->score_breakdown, true) ?: [];
                DB::table('tournament_games')->where('id', $g->id)->update([
                    'player_one_strategy_vp' => $b['player_one']['strategy'] ?? null,
                    'player_one_scheme_vp' => $b['player_one']['scheme'] ?? null,
                    'player_two_strategy_vp' => $b['player_two']['strategy'] ?? null,
                    'player_two_scheme_vp' => $b['player_two']['scheme'] ?? null,
                ]);
            }
        });

        Schema::table('tournament_games', function (Blueprint $table) {
            $table->dropColumn('score_breakdown');
        });
    }
};
