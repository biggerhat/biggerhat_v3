<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Catalog Consolidation — Phase 1b: Add totem-template columns to
 * `custom_characters` so the `totem_catalog` table can be absorbed.
 *
 * The existing `is_campaign_totem` column flags per-leader totem *instances*.
 * The new `is_campaign_totem_template` flags system-owned *template* rows
 * that the Aftermath totem-advancement picker reads from.
 *
 * Phase 2 (next migration) copies totem_catalog rows in as template rows
 * owned by a system user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            // Template flag — distinguishes the system-owned bank of totem
            // templates from per-leader minted totem instances.
            $table->boolean('is_campaign_totem_template')->default(false)->after('is_campaign_totem');

            // Flip-value gating for the Aftermath totem-advancement picker.
            // Nullable because joker entries (Sniveling Coward / Mini-Master)
            // don't have a flip value.
            $table->unsignedTinyInteger('campaign_totem_flip_value')->nullable()->after('is_campaign_totem_template');

            // Special-case rules from the totem catalog. Both null/false on
            // ordinary totems; true on the matching joker template.
            $table->boolean('campaign_is_black_joker_totem')->default(false)->after('campaign_totem_flip_value');
            $table->boolean('campaign_is_red_joker_totem')->default(false)->after('campaign_is_black_joker_totem');
            $table->boolean('campaign_totem_special_replace')->default(false)->after('campaign_is_red_joker_totem');
            $table->boolean('campaign_is_mini_master')->default(false)->after('campaign_totem_special_replace');

            $table->index(
                ['is_campaign_totem_template', 'campaign_totem_flip_value'],
                'idx_cc_totem_tmpl_flip'
            );
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropIndex('idx_cc_totem_tmpl_flip');
            $table->dropColumn([
                'is_campaign_totem_template',
                'campaign_totem_flip_value',
                'campaign_is_black_joker_totem',
                'campaign_is_red_joker_totem',
                'campaign_totem_special_replace',
                'campaign_is_mini_master',
            ]);
        });
    }
};
