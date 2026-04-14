<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->json('linked_crew_upgrades')->nullable()->after('characteristics');
            $table->json('linked_totems')->nullable()->after('linked_crew_upgrades');
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn(['linked_crew_upgrades', 'linked_totems']);
        });
    }
};
