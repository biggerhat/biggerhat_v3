<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-add the `faction` column on tournament_rsvps. Originally dropped in
     * the 2026-04-13 cleanup as unused — now we want users to optionally
     * declare which faction they intend to play during RSVP so TOs can plan
     * meta balance / scenario painting before the player list is finalized.
     */
    public function up(): void
    {
        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->string('faction')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_rsvps', function (Blueprint $table) {
            $table->dropColumn('faction');
        });
    }
};
