<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a Company hire pick which physical sculpt variant of a unit they're
 * fielding (e.g. v1 vs anniversary repaint). Mirrors the Malifaux Crew
 * Builder's `member.miniature_id` selection — the company-builder drawer
 * exposes a sculpt-picker dropdown that writes here.
 *
 * Nullable: legacy rows + units with only one sculpt skip the selection
 * and fall back to the unit's first sculpt at render time.
 *
 * `nullOnDelete` so deleting a sculpt doesn't cascade-destroy the company
 * row — the company keeps the unit hire, just loses the explicit pick.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_company_units', function (Blueprint $table) {
            $table->foreignId('sculpt_id')
                ->nullable()
                ->after('unit_id')
                ->constrained('tos_unit_sculpts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tos_company_units', function (Blueprint $table) {
            $table->dropForeign(['sculpt_id']);
            $table->dropColumn('sculpt_id');
        });
    }
};
