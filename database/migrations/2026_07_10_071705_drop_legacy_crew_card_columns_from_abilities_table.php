<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `campaign_crew_cards` (see create_campaign_crew_cards_table) fully replaced
 * the interim approach of tagging Ability rows as crew card effects. These
 * columns have been dead since — no controller, service, or Vue page reads
 * them anymore.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite requires the covering index gone before the column drop.
        if (Schema::hasIndex('abilities', 'idx_ab_mode_crew_card')) {
            Schema::table('abilities', fn (Blueprint $table) => $table->dropIndex('idx_ab_mode_crew_card'));
        }

        Schema::table('abilities', function (Blueprint $table) {
            $table->dropColumn(['is_crew_card_effect', 'requires_token_choice', 'requires_marker_choice', 'requires_upgrade_type_choice']);
        });
    }

    public function down(): void
    {
        Schema::table('abilities', function (Blueprint $table) {
            $table->boolean('is_crew_card_effect')->default(false);
            $table->boolean('requires_token_choice')->default(false);
            $table->boolean('requires_marker_choice')->default(false);
            $table->boolean('requires_upgrade_type_choice')->default(false);
        });

        if (! Schema::hasIndex('abilities', 'idx_ab_mode_crew_card')) {
            Schema::table('abilities', fn (Blueprint $table) => $table->index(['game_mode_type', 'is_crew_card_effect'], 'idx_ab_mode_crew_card'));
        }
    }
};
