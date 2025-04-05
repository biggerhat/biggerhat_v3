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
        Schema::create('triggers', function (Blueprint $table) {
            $table->id();
            $table->string('suits');
            $table->string('name');
            $table->string('slug');
            $table->longText('description');
            $table->longText('internal_notes');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('action_trigger', function (Blueprint $table) {
            $table->foreignId('action_id')->constrained('actions')->cascadeOnDelete();
            $table->foreignId('trigger_id')->constrained('triggers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_trigger');
        Schema::dropIfExists('triggers');
    }
};
