<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An opposed duel (CRG p.10): the attacking flip and the resisting flip are linked, and each side enters its duel total (the flip plus the
        // stat), so the cheating order can be followed: the lowest total cheats first, the resister on a tie, then the other player.
        Schema::table('game_fate_flips', function (Blueprint $table) {
            $table->string('duel_role', 10)->nullable()->after('subject'); // attacker | resister
            $table->unsignedBigInteger('duel_with')->nullable()->after('duel_role'); // the opposing flip
            $table->smallInteger('total')->nullable()->after('duel_with');
        });
    }

    public function down(): void
    {
        Schema::table('game_fate_flips', function (Blueprint $table) {
            $table->dropColumn(['duel_role', 'duel_with', 'total']);
        });
    }
};
