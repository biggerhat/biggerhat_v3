<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign crew builds have a CustomCharacter master (not a catalog Character),
 * so master_id is null for the synthetic crew builds created by
 * GameSetupController::submitCampaignCrew. Making the column nullable allows
 * those builds while keeping non-null enforcement for standard crew builds at
 * the application layer (FormRequest validation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropForeignSafe('crew_builds_master_id_foreign');
            $table->foreignId('master_id')->nullable()->change()->constrained('characters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropForeignSafe('crew_builds_master_id_foreign');
            $table->foreignId('master_id')->change()->constrained('characters')->cascadeOnDelete();
        });
    }
};
