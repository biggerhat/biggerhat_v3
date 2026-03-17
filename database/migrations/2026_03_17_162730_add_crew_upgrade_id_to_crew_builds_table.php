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
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->foreignId('crew_upgrade_id')->nullable()->after('crew_data')->constrained('upgrades')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crew_upgrade_id');
        });
    }
};
