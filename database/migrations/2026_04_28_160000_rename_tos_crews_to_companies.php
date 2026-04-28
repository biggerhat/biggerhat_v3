<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Other Side rulebook calls a roster a "Company", not a "Crew" — that
 * was a stand-in name borrowed from the Malifaux side during early Phase 1
 * scaffolding. This migration renames the three TOS tables and the FK
 * columns to match the rulebook nomenclature. The Malifaux `crews` table
 * (used by `Tools/CrewBuilder`) is untouched.
 *
 * Done as raw RENAMEs because Schema::rename keeps both indexes and FKs
 * pointing at the new table name without us having to drop/re-add them.
 * Down migration is the symmetric inverse so a rollback restores the old
 * naming exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rename tables.
        Schema::rename('tos_crews', 'tos_companies');
        Schema::rename('tos_crew_units', 'tos_company_units');
        Schema::rename('tos_crew_unit_assets', 'tos_company_unit_assets');

        // Rename FK columns. Schema::table->renameColumn keeps the FK
        // intact since MySQL stores the FK on the referenced column, not
        // its name.
        Schema::table('tos_company_units', function ($table) {
            $table->renameColumn('crew_id', 'company_id');
        });

        Schema::table('tos_company_unit_assets', function ($table) {
            $table->renameColumn('crew_unit_id', 'company_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('tos_company_unit_assets', function ($table) {
            $table->renameColumn('company_unit_id', 'crew_unit_id');
        });

        Schema::table('tos_company_units', function ($table) {
            $table->renameColumn('company_id', 'crew_id');
        });

        Schema::rename('tos_company_unit_assets', 'tos_crew_unit_assets');
        Schema::rename('tos_company_units', 'tos_crew_units');
        Schema::rename('tos_companies', 'tos_crews');
    }
};
