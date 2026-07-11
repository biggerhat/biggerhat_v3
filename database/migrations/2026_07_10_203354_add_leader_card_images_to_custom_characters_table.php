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
        // Server-rendered card images (front/back/combination — same shape as
        // Miniature/Upgrade's), populated only for Campaign Leaders/Totems
        // (is_campaign_leader/is_campaign_totem) via a headless-Chrome capture
        // triggered whenever the character's card-relevant data changes — see
        // App\Services\Campaign\LeaderCardImageGenerator. Generic homebrew
        // Custom Card Creator characters stay client-side-render-only (no
        // server image baking), unaffected by this column addition.
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->string('front_image')->nullable()->after('linked_totems');
            $table->string('back_image')->nullable()->after('front_image');
            $table->string('combination_image')->nullable()->after('back_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn(['front_image', 'back_image', 'combination_image']);
        });
    }
};
