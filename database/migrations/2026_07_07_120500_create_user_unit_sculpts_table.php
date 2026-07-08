<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_unit_sculpts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_sculpt_id')->constrained('tos_unit_sculpts')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_built')->default(false);
            $table->boolean('is_painted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'unit_sculpt_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_unit_sculpts');
    }
};
