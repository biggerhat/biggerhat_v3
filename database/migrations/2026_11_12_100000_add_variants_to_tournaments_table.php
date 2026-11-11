<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Optional rule variants the TO enabled, keyed by the ruleset's variant keys:
            // {"alternating_leaders": true, "bans": 2}. Players see these before the event.
            $table->json('variants')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('variants');
        });
    }
};
