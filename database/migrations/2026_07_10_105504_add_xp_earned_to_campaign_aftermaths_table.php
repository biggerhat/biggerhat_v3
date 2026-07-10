<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how many XP-track boxes Phase 4 (Advance Leader) filled this
 * aftermath — mirrors the existing `scrip_earned` pattern for Payday.
 * Needed so "go back a phase" can unfill exactly those boxes rather than
 * guessing; XP boxes always fill in ascending index order, so unfilling the
 * last N filled boxes is safe as long as nothing else fills boxes between
 * this aftermath's Phase 4 submit and an immediate undo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_aftermaths', function (Blueprint $table) {
            $table->unsignedTinyInteger('xp_earned')->nullable()->after('scrip_earned');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermaths', function (Blueprint $table) {
            $table->dropColumn('xp_earned');
        });
    }
};
