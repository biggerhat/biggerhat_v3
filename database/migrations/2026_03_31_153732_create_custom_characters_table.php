<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('share_code', 12)->unique();
            $table->boolean('is_public')->default(false);

            // Identity
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('display_name');
            $table->string('slug');

            // Faction & station
            $table->string('faction');
            $table->string('second_faction')->nullable();
            $table->string('station')->nullable();

            // Stats
            $table->unsignedTinyInteger('cost')->nullable();
            $table->unsignedTinyInteger('health');
            $table->unsignedTinyInteger('size')->nullable();
            $table->string('base')->default('30');
            $table->unsignedTinyInteger('defense');
            $table->string('defense_suit')->nullable();
            $table->unsignedTinyInteger('willpower');
            $table->string('willpower_suit')->nullable();
            $table->unsignedTinyInteger('speed');
            $table->unsignedTinyInteger('count')->default(1);
            $table->unsignedTinyInteger('summon_target_number')->nullable();
            $table->boolean('generates_stone')->default(false);
            $table->boolean('is_unhirable')->default(false);

            // Images
            $table->string('character_image')->nullable();
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();

            // Content (JSON)
            $table->json('actions')->nullable();
            $table->json('abilities')->nullable();
            $table->json('keywords')->nullable();
            $table->json('characteristics')->nullable();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('faction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_characters');
    }
};
