<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->string('faction')->nullable()->after('display_name');
        });

        // Backfill existing crew members from their character's faction
        \App\Models\GameCrewMember::whereNull('faction')
            ->whereNotNull('character_id')
            ->each(function (\App\Models\GameCrewMember $m) {
                $faction = \App\Models\Character::where('id', $m->character_id)->value('faction');
                if ($faction) {
                    $m->update(['faction' => $faction]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropColumn('faction');
        });
    }
};
