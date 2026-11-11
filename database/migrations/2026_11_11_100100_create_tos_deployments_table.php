<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_deployments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->unique();
            // The Fate suit the Defender flips to determine this Deployment: R, C, T or M.
            // A flipped Joker is treated as R (Confrontation), so each suit maps to one Deployment.
            $table->string('suit', 1)->nullable()->unique();
            $table->longText('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_deployments');
    }
};
