<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Public, reusable "anyone with this link can join" invite —
            // nullable+unique like the existing share_code precedent
            // (custom_characters, tos_companies). No backfill: existing
            // campaigns lazily get one on first view (CampaignController::show()),
            // mirroring the app's existing solo-crew backfill idiom there.
            $table->string('uuid')->nullable()->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
