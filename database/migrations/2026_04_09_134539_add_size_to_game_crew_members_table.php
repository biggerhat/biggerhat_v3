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
            $table->unsignedTinyInteger('size')->nullable()->after('speed');
        });

        // Backfill from character data for existing crew members
        if (GameCrewMember::count() > 0) {
            GameCrewMember::whereNotNull('character_id')
                ->whereNull('size')
                ->chunkById(100, function ($members) {
                    $characterIds = $members->pluck('character_id')->unique();
                    $characters = \App\Models\Character::whereIn('id', $characterIds)
                        ->get(['id', 'size'])
                        ->keyBy('id');

                    foreach ($members as $member) {
                        $char = $characters->get($member->character_id);
                        if ($char) {
                            $member->update(['size' => $char->size]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
