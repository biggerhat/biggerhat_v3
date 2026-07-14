<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server-generated card image (App\Services\Campaign\CrewCardImageGenerator),
     * mirroring how Leader/Totem cards are rendered — a single face is enough
     * (unlike a full stat card, a Crew Card is just name + rules text).
     */
    public function up(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->string('front_image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropColumn('front_image');
        });
    }
};
