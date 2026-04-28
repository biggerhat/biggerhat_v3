<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three FK columns hit on every Company View / addUnit / attachAsset render
 * that lacked their own index — Laravel's `foreignId()->constrained()` makes
 * the FK constraint but no separate index on MySQL.
 *
 *   • `tos_companies.allegiance_id` — Company View loads the allegiance once
 *     per request; addUnit hireability filters scope by it.
 *   • `tos_company_units.unit_id` — every Combined Arms parent/child lookup
 *     and every "is this Asset already attached anywhere" check joins back
 *     to it.
 *   • `tos_company_unit_assets.asset_id` — `Company::hasAssetAttached()` and
 *     the per-Company Unique-cap check both filter by asset_id.
 *
 * The compound `(crew_id, position)` index from the original create helps
 * leading-key queries on company_id but doesn't cover any of the above.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->index('allegiance_id');
        });

        Schema::table('tos_company_units', function (Blueprint $table) {
            $table->index('unit_id');
        });

        Schema::table('tos_company_unit_assets', function (Blueprint $table) {
            $table->index('asset_id');
        });

        // tos_action_trigger has a unique (action_id, trigger_id) which covers
        // forward lookups (actions of a given trigger), but reverse lookups —
        // "every action this trigger is attached to" — leave the leading column
        // unindexed. Trigger::actions() relation walks this column.
        Schema::table('tos_action_trigger', function (Blueprint $table) {
            $table->index('trigger_id');
        });
    }

    public function down(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->dropIndex(['allegiance_id']);
        });

        Schema::table('tos_company_units', function (Blueprint $table) {
            $table->dropIndex(['unit_id']);
        });

        Schema::table('tos_company_unit_assets', function (Blueprint $table) {
            $table->dropIndex(['asset_id']);
        });

        Schema::table('tos_action_trigger', function (Blueprint $table) {
            $table->dropIndex(['trigger_id']);
        });
    }
};
