<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Henchman is a Characteristic, not a Station — null out any characters with station='henchman'
        DB::table('characters')->where('station', 'henchman')->update(['station' => null]);
        DB::table('game_crew_members')->where('station', 'henchman')->update(['station' => null]);
    }

    public function down(): void
    {
        // Not reversible — henchman station data cannot be reliably restored
    }
};
