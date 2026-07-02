<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if (DB::getDriverName() === 'mysql') {
            // Single ALTER avoids a full table rebuild (Doctrine DBAL ->change() rebuilds the table).
            DB::statement('ALTER TABLE crew_builds
                DROP FOREIGN KEY crew_builds_master_id_foreign,
                MODIFY COLUMN master_id BIGINT UNSIGNED NULL,
                ADD CONSTRAINT crew_builds_master_id_foreign
                    FOREIGN KEY (master_id) REFERENCES characters(id) ON DELETE CASCADE');
        } else {
            Schema::table('crew_builds', function (Blueprint $table) {
                $table->unsignedBigInteger('master_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE crew_builds
                DROP FOREIGN KEY crew_builds_master_id_foreign,
                MODIFY COLUMN master_id BIGINT UNSIGNED NOT NULL,
                ADD CONSTRAINT crew_builds_master_id_foreign
                    FOREIGN KEY (master_id) REFERENCES characters(id) ON DELETE CASCADE');
        } else {
            Schema::table('crew_builds', function (Blueprint $table) {
                $table->unsignedBigInteger('master_id')->nullable(false)->change();
            });
        }
    }
};
