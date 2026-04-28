<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Allegiances — those that print BOTH Earth and Malifaux on their
 * Card — need to expose both type-derived hireability pools at once. The
 * cleanest schema for a 2-cardinality field is one extra nullable enum
 * column rather than a JSON array or pivot table; everything still keys
 * off the existing `type` column when only one side applies, and code that
 * cares about the "all sides this Allegiance is on" question reaches for
 * the new `Allegiance::types()` helper which folds in `secondary_type`
 * when set.
 *
 * Single-type Allegiances have `secondary_type = null` (the default),
 * which means the existing pre-hybrid query semantics keep working
 * unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_allegiances', function (Blueprint $table) {
            $table->string('secondary_type', 16)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tos_allegiances', function (Blueprint $table) {
            $table->dropColumn('secondary_type');
        });
    }
};
