<?php

use App\Models\Game;
use App\Models\GameTurn;
use App\Models\Scheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game_players: add next_scheme_id and scheme_pool
        Schema::table('game_players', function (Blueprint $table) {
            $table->foreignId('next_scheme_id')->nullable()->after('current_scheme_id')->constrained('schemes')->nullOnDelete();
            $table->json('scheme_pool')->nullable()->after('scheme_notes');
        });

        // game_turns: add scheme_action and next_scheme_id
        Schema::table('game_turns', function (Blueprint $table) {
            $table->string('scheme_action')->nullable()->after('scheme_id'); // scored, held, discarded
            $table->foreignId('next_scheme_id')->nullable()->after('scheme_action')->constrained('schemes')->nullOnDelete();
        });

        // Backfill existing data
        $this->backfill();
    }

    private function backfill(): void
    {
        // Backfill scheme_action on existing turns
        $turns = GameTurn::whereNotNull('scheme_id')->get()->groupBy('game_player_id');

        foreach ($turns as $playerTurns) {
            $sorted = $playerTurns->sortBy('turn_number')->values();
            for ($i = 0; $i < $sorted->count(); $i++) {
                $turn = $sorted[$i];
                if ($turn->scheme_points > 0) {
                    $turn->update(['scheme_action' => 'scored']);
                } else {
                    // Check if next turn has a different scheme (discarded)
                    $nextTurn = $sorted[$i + 1] ?? null;
                    if ($nextTurn && $nextTurn->scheme_id && $nextTurn->scheme_id !== $turn->scheme_id) {
                        $turn->update(['scheme_action' => 'discarded']);
                    } else {
                        $turn->update(['scheme_action' => 'held']);
                    }
                }
            }
        }

        // Backfill scheme_pool on in-progress game players
        $inProgressGames = Game::where('status', 'in_progress')->with('players')->get();
        $schemeCache = Scheme::all()->keyBy('id');

        foreach ($inProgressGames as $game) {
            $gamePool = $game->scheme_pool ?? [];

            foreach ($game->players as $player) {
                if ($player->current_scheme_id) {
                    // Player has a scheme — pool is that scheme's follow-up chain
                    $scheme = $schemeCache->get($player->current_scheme_id);
                    if ($scheme) {
                        $pool = array_values(array_filter([
                            $scheme->next_scheme_one_id,
                            $scheme->next_scheme_two_id,
                            $scheme->next_scheme_three_id,
                        ]));
                        // If no follow-ups, keep game pool
                        $player->update(['scheme_pool' => ! empty($pool) ? $pool : $gamePool]);
                    } else {
                        $player->update(['scheme_pool' => $gamePool]);
                    }
                } else {
                    // No scheme yet — full game pool
                    $player->update(['scheme_pool' => $gamePool]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropForeign(['next_scheme_id']);
            $table->dropColumn(['next_scheme_id', 'scheme_pool']);
        });

        Schema::table('game_turns', function (Blueprint $table) {
            $table->dropForeign(['next_scheme_id']);
            $table->dropColumn(['scheme_action', 'next_scheme_id']);
        });
    }
};
