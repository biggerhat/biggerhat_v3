<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PR 4.5 — rulebook-alignment gaps (items 1–9 from audit):
 *   1. Actions can carry multiple types → pivot tos_action_types.
 *   2. `damage` → `strength` on tos_actions (smallint nullable).
 *   3. `av_suits` on tos_actions (e.g. "R" for a 6R v Df Action).
 *   4. Damage types (Piercing / Accurate / Area) as bool columns on tos_actions.
 *   5. Trigger margin cost.
 *   6. Trigger timing (default / immediately).
 *   7. "Once per" usage_limit on tos_actions and tos_abilities.
 *   8. Asset baseline allegiance-match — model-layer only, no schema change.
 *   9. Slot scope comment fix — code-only, no schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Actions: multi-type via pivot.
        Schema::create('tos_action_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['action_id', 'type']);
        });

        // Copy existing singular `type` into the pivot before dropping it so
        // mid-dev seed data survives the migration.
        DB::table('tos_actions')->orderBy('id')->get()->each(function ($row) {
            if ($row->type) {
                DB::table('tos_action_types')->insertOrIgnore([
                    'action_id' => $row->id,
                    'type' => $row->type,
                    'sort_order' => 0,
                ]);
            }
        });

        // 2–4, 7: actions column changes.
        Schema::table('tos_actions', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('tos_actions', function (Blueprint $table) {
            // Rename damage → strength AND switch type to smallint. Migrate
            // the string value in a best-effort numeric parse below.
            $table->unsignedTinyInteger('strength')->nullable()->after('range');
            $table->string('av_suits', 8)->nullable()->after('av_target');
            $table->boolean('is_piercing')->default(false)->after('strength');
            $table->boolean('is_accurate')->default(false)->after('is_piercing');
            $table->boolean('is_area')->default(false)->after('is_accurate');
            $table->string('usage_limit', 32)->nullable()->after('is_area');
        });

        // Best-effort data move from old `damage` string → new `strength` int.
        DB::table('tos_actions')->orderBy('id')->get()->each(function ($row) {
            if (! property_exists($row, 'damage') || $row->damage === null) {
                return;
            }
            if (preg_match('/(\d+)/', (string) $row->damage, $m)) {
                DB::table('tos_actions')->where('id', $row->id)->update(['strength' => (int) $m[1]]);
            }
        });

        Schema::table('tos_actions', function (Blueprint $table) {
            $table->dropColumn('damage');
        });

        // 5 + 6: trigger columns.
        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->unsignedTinyInteger('margin_cost')->nullable()->after('suits');
            $table->string('timing', 16)->default('default')->after('margin_cost');
        });

        // 7: ability usage_limit.
        Schema::table('tos_abilities', function (Blueprint $table) {
            $table->string('usage_limit', 32)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('tos_abilities', function (Blueprint $table) {
            $table->dropColumn('usage_limit');
        });

        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->dropColumn(['margin_cost', 'timing']);
        });

        Schema::table('tos_actions', function (Blueprint $table) {
            $table->string('damage', 32)->nullable()->after('range');
        });

        Schema::table('tos_actions', function (Blueprint $table) {
            $table->dropColumn(['strength', 'av_suits', 'is_piercing', 'is_accurate', 'is_area', 'usage_limit']);
            $table->string('type')->default('melee')->after('name');
        });

        Schema::dropIfExists('tos_action_types');
    }
};
