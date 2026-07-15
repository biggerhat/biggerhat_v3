<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier-4 Crew Card Advancement (pg 32) grants ONE effect — "'effect' refers
 * to a single ability, action, or trigger" — not an entire Crew Card
 * Upgrade's contents. `crew_card_effect_id`/`_type` still identify which
 * source card the effect was borrowed from (needed for the restriction
 * pivot lookup — "the effect comes with any limitations it had on the
 * original crew card"), while these two new columns pin down exactly WHICH
 * item on that card was picked.
 *
 * Nullable, and only ever populated for `crew_card_effect_type` = Upgrade
 * (the keyword-matched pool) — the generic pg 15-16 catalog
 * (CampaignCrewCard source) stays whole-row, since each admin-authored row
 * there already models a single named effect. Existing crew_upgrade rows
 * predate this granularity change and have no way to know which single item
 * a player would have picked, so they're left null and treated as a legacy
 * "this crew already holds everything this card granted" grant everywhere
 * these columns are read — no backfill, no data loss for crews that already
 * spent that advancement box.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->string('crew_card_item_type')->nullable()->after('crew_card_effect_type');
            $table->unsignedBigInteger('crew_card_item_id')->nullable()->after('crew_card_item_type');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropColumn(['crew_card_item_type', 'crew_card_item_id']);
        });
    }
};
