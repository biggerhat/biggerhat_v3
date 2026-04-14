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
        Schema::create('custom_upgrades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('share_code', 12)->unique();
            $table->boolean('is_public')->default(false);

            // Identity
            $table->string('name');
            $table->string('display_name');
            $table->string('slug');

            // Classification
            $table->string('domain'); // crew or character
            $table->string('type')->nullable(); // UpgradeTypeEnum
            $table->string('faction')->nullable();
            $table->string('limitations')->nullable(); // UpgradeLimitationEnum

            // Stats
            $table->tinyInteger('plentiful')->nullable();

            // Crew upgrade specific
            $table->string('master_name')->nullable();
            $table->string('keyword_name')->nullable();

            // Content (JSON)
            $table->json('abilities')->nullable();
            $table->json('actions')->nullable();

            // Crew upgrade back face
            $table->json('back_tokens')->nullable();
            $table->json('back_actions')->nullable();
            $table->text('restrictions')->nullable();

            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_upgrades');
    }
};
