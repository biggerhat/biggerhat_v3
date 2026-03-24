<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('encounter_size')->default(50);
            $table->unsignedTinyInteger('planned_rounds');
            $table->string('season');
            $table->string('status')->default('draft');
            $table->boolean('is_public')->default(false);
            $table->string('location')->nullable();
            $table->date('event_date');
            $table->unsignedInteger('round_time_limit')->default(135);
            $table->timestamps();

            $table->index(['status', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
