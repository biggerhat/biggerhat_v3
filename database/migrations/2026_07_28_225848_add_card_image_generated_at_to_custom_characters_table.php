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
        Schema::table('custom_characters', function (Blueprint $table) {
            // Set only when LeaderCardImageGenerator::generate() actually
            // finishes rendering — unlike updated_at (bumped synchronously by
            // the DB save that dispatches the async render job, before it
            // even starts), this is the one reliable "the new image is ready"
            // signal for the Arsenal Sheet's regeneration poll.
            $table->timestamp('card_image_generated_at')->nullable()->after('combination_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn('card_image_generated_at');
        });
    }
};
