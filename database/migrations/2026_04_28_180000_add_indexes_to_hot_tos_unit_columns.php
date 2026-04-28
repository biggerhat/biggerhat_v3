<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two `tos_units` columns are filtered on every Company/Search/Compare
 * page render but never had supporting indexes:
 *
 *   • `combined_arms_child_id` — every `Unit::notCombinedArmsChild()`
 *     scope produces `WHERE NOT EXISTS (combinedArmsParent)` which joins
 *     back to this column. The column is also a foreign key, but Laravel's
 *     `foreignId()->constrained()` builds the FK without a separate index
 *     on MySQL.
 *   • `restriction` — `Unit::scopeHireableInto()` `whereIn`s this on every
 *     Company-builder hireable lookup and Advanced Search query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_units', function (Blueprint $table) {
            $table->index('combined_arms_child_id');
            $table->index('restriction');
        });
    }

    public function down(): void
    {
        Schema::table('tos_units', function (Blueprint $table) {
            $table->dropIndex(['combined_arms_child_id']);
            $table->dropIndex(['restriction']);
        });
    }
};
