<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->string('back_image')->nullable()->after('front_image');
        });
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropColumn('back_image');
        });
    }
};
