<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_operations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            // Where the Operation comes from, e.g. "Fields of Glory v3.0" or "Core Rulebook".
            $table->string('source')->nullable();
            $table->text('flavor')->nullable();
            $table->longText('additional_setup')->nullable();
            $table->longText('special_rules')->nullable();
            $table->longText('victory_conditions')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_operations');
    }
};
