<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the interim approach of storing crew card effects as Ability rows
 * with is_crew_card_effect=true. Crew cards in the Index of the Untold are
 * richer than a single ability description — they can include ability blocks,
 * actions, and other structured data. A dedicated table is cleaner and avoids
 * polluting the core Ability catalog with campaign-mode specialisations.
 *
 * campaign_crews.crew_card_effect_id is re-pointed here; the old abilities
 * is_crew_card_effect flag remains on the abilities table but is no longer
 * used by the starting-arsenal flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_crew_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_token_choice')->default(false);
            $table->boolean('requires_marker_choice')->default(false);
            $table->boolean('requires_upgrade_type_choice')->default(false);
            $table->timestamps();
        });

        // Re-point the FK on campaign_crews. The column already exists; we
        // just swap what it points at (abilities → campaign_crew_cards).
        // Wipe any stale references first so the FK can be created cleanly.
        \DB::table('campaign_crews')->update(['crew_card_effect_id' => null]);

        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->foreign('crew_card_effect_id', 'cc_crew_card_fk')
                ->references('id')->on('campaign_crew_cards')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crews', function (Blueprint $table) {
            $table->dropForeign('cc_crew_card_fk');
        });

        Schema::dropIfExists('campaign_crew_cards');
    }
};
