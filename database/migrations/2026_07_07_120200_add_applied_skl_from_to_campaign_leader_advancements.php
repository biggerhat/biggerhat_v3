<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captures the leader action's actual Skl value at the moment a Skl Boost
 * advancement (pg 38-43) was applied. The catalog row's skl_from is a
 * qualifying range (e.g. "Skl of 0 or 1"), not necessarily the action's exact
 * prior value, so undoing the advancement can't infer it from the catalog
 * row alone — it has to be captured up front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->unsignedTinyInteger('applied_skl_from')->nullable()->after('applied_to_action_index');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_leader_advancements', function (Blueprint $table) {
            $table->dropColumn('applied_skl_from');
        });
    }
};
