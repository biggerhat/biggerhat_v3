<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per physical model a user owns, with how far along it is. `user_miniatures` stays as the "I own this miniature"
     * row (its quantity and built/painted flags are kept in step with these rows).
     */
    public function up(): void
    {
        Schema::create('collection_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('miniature_id')->constrained('miniatures')->cascadeOnDelete();
            $table->string('stage', 16)->default('unbuilt');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'miniature_id'], 'collection_models_user_miniature_index');
            $table->index(['user_id', 'stage'], 'collection_models_user_stage_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_models');
    }
};
