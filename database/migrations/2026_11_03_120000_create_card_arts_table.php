<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-curated library of character art for the Custom Card Creator. Players pick from this library instead of
 * uploading their own image (uploads aren't persisted, and un-vetted images are a moderation risk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_arts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // FactionEnum value when the art suits one faction; null = usable by any.
            $table->string('faction')->nullable()->index();
            // Free-text grouping shown as a filter chip in the picker (e.g. "Human", "Construct", "Beast").
            $table->string('category')->nullable();
            $table->string('image_path');
            $table->string('credit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'card_arts_active_sort_idx');
        });

        Schema::table('custom_characters', function (Blueprint $table) {
            $table->foreignId('card_art_id')->nullable()->after('is_unhirable')->constrained('card_arts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropForeignSafe('custom_characters', ['card_art_id']);
        });

        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn('card_art_id');
        });

        Schema::dropIfExists('card_arts');
    }
};
