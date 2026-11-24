<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // tracked = played through the Game Tracker; logged = played elsewhere and entered by hand afterwards
            $table->string('origin')->default('tracked')->after('format')->index('games_origin_index');
            $table->text('notes')->nullable()->after('name');
            // where it was played or what event it belonged to ("Friday league", "Gencon"); free text
            $table->string('venue', 120)->nullable()->after('notes');
            // logged games are private to their owner unless this is on; tracked games keep their existing visibility
            $table->boolean('is_public')->default(false)->after('is_observable');
        });

        Schema::table('game_players', function (Blueprint $table) {
            // a logged game can name an opponent's account; the link only counts once they confirm it (then user_id is set)
            $table->foreignId('linked_user_id')->nullable()->after('opponent_name')
                ->constrained('users', indexName: 'game_players_linked_user_fk')->nullOnDelete();
            $table->string('link_status', 12)->nullable()->after('linked_user_id'); // pending | confirmed | declined
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropForeignSafe('game_players_linked_user_fk');
            $table->dropColumn(['linked_user_id', 'link_status']);
        });
        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex('games_origin_index');
            $table->dropColumn(['origin', 'notes', 'venue', 'is_public']);
        });
    }
};
