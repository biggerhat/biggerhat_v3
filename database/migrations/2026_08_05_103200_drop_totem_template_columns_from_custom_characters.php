<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up to 2026_08_05_103100_create_campaign_totem_templates_table —
 * separate migration so the data-copy step there never runs against an
 * already-trimmed source table. Drops the totem-template-only columns from
 * custom_characters now that every such row has moved to
 * campaign_totem_templates; is_campaign_totem (the per-crew hired instance
 * flag) and every other column stay untouched.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->boolean('is_campaign_totem_template')->default(false)->after('is_campaign_totem');
            $table->unsignedTinyInteger('campaign_totem_flip_value')->nullable()->after('is_campaign_totem_template');
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
};
