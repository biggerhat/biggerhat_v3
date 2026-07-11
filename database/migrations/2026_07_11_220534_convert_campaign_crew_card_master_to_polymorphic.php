<?php

use App\Models\Character;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign Leaders can be entirely custom-built (CustomCharacter), not just
 * official masters — but a Crew Card's "master this is actually printed on"
 * (`campaign_crew_cards.master_id`) and a borrowed effect's attribution
 * (`campaign_crew_card_advancements.source_master_id`) were both hard FKs to
 * `characters` only, so a custom Leader could never be assigned as a Crew
 * Card's master or credited as the source of a borrowed effect. Converts
 * both to polymorphic (`{column}_id` + a new `{column}_type`), following the
 * same `morphTo()` pattern already used for `wishlist_items.wishlistable`.
 * Existing rows all point at real masters today, so they backfill to
 * `App\Models\Character` — no morph map is registered anywhere in this app
 * (`wishlistable_type` stores raw FQCNs too), so this matches that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->string('master_type')->nullable()->after('master_id');
        });
        DB::table('campaign_crew_cards')->whereNotNull('master_id')->update(['master_type' => Character::class]);

        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropForeign(['source_master_id']);
            $table->string('source_master_type')->nullable()->after('source_master_id');
        });
        DB::table('campaign_crew_card_advancements')->whereNotNull('source_master_id')->update(['source_master_type' => Character::class]);
    }

    public function down(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropColumn('master_type');
        });
        Schema::table('campaign_crew_card_advancements', function (Blueprint $table) {
            $table->dropColumn('source_master_type');
        });

        // FK constraints intentionally not restored — any custom-master rows
        // written while this migration was up would violate them. Re-add
        // manually after confirming no custom-typed rows remain, if needed.
    }
};
