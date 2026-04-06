<?php

use App\Models\GameCrewMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->unsignedTinyInteger('defense')->nullable()->after('max_health');
            $table->unsignedTinyInteger('willpower')->nullable()->after('defense');
            $table->unsignedTinyInteger('speed')->nullable()->after('willpower');
        });

        // Backfill from character data for existing crew members
        GameCrewMember::whereNotNull('character_id')
            ->whereNull('defense')
            ->chunkById(100, function ($members) {
                $characterIds = $members->pluck('character_id')->unique();
                $characters = \App\Models\Character::whereIn('id', $characterIds)
                    ->get(['id', 'defense', 'willpower', 'speed'])
                    ->keyBy('id');

                foreach ($members as $member) {
                    $char = $characters->get($member->character_id);
                    if ($char) {
                        $member->update([
                            'defense' => $char->defense,
                            'willpower' => $char->willpower,
                            'speed' => $char->speed,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropColumn(['defense', 'willpower', 'speed']);
        });
    }
};
