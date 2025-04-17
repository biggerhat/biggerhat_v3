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
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('display_name');
            $table->string('slug');
            $table->string('nicknames')->nullable();
            $table->string('faction');
            $table->string('second_faction')->nullable();
            $table->string('station');
            $table->integer('cost');
            $table->integer('health');
            $table->integer('size');
            $table->integer('base');
            $table->integer('defense');
            $table->string('defense_suit')->nullable();
            $table->integer('willpower');
            $table->string('willpower_suit')->nullable();
            $table->integer('speed');
            $table->integer('count')->default(1);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_dead')->default(false);
            $table->boolean('is_unhirable')->default(false);
            $table->boolean('is_beta')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('characterables', function (Blueprint $table) {
            $table->morphs('characterable');
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characterables');
        Schema::dropIfExists('characters');
    }
};
