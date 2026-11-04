<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saved front / back / combined card images for custom upgrades and crew cards, same shape as custom_characters. */
    public function up(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('combination_image')->nullable();
            $table->timestamp('card_image_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->dropColumn(['front_image', 'back_image', 'combination_image', 'card_image_generated_at']);
        });
    }
};
