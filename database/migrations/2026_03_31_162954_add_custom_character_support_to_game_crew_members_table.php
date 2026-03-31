<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            // Make character_id nullable for custom characters
            $table->unsignedBigInteger('character_id')->nullable()->change();

            // Add custom_character_id (nullable FK)
            $table->foreignId('custom_character_id')->nullable()->after('character_id')
                ->constrained('custom_characters')->nullOnDelete();

            // Track whether this is a custom character
            $table->boolean('is_custom')->default(false)->after('is_activated');
        });
    }

    public function down(): void
    {
        Schema::table('game_crew_members', function (Blueprint $table) {
            $table->dropForeign(['custom_character_id']);
            $table->dropColumn(['custom_character_id', 'is_custom']);
        });
    }
};
