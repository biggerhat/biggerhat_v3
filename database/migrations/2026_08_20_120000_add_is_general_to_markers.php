<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markers', function (Blueprint $table) {
            // Mirrors tokens.is_general (2026_06_23_202128) — "general"
            // markers (Scheme, Strategy, Remains, …) are universal Malifaux
            // game markers, not something a specific crew's equipment/
            // actions/abilities grant, so they're excluded from a crew's own
            // Tokens/Markers quick-reference (CombinedCrewCardEffects::
            // arsenalTokensAndMarkers()) even when the heuristic linker
            // (LinkTokensAndMarkers) attaches them via a text-mention match.
            $table->boolean('is_general')->default(false)->index()->after('icon');
        });

        DB::table('markers')->whereIn('name', ['Scheme', 'Strategy', 'Remains'])->update(['is_general' => true]);
    }

    public function down(): void
    {
        Schema::table('markers', function (Blueprint $table) {
            $table->dropColumn('is_general');
        });
    }
};
