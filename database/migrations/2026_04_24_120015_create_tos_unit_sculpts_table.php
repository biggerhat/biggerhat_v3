<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_unit_sculpts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            // Mirrors Malifaux miniatures column-for-column: standard-side art,
            // glory-side art, and the merged/promo image.
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('combination_image')->nullable();
            $table->date('release_date')->nullable();
            $table->string('box_reference')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_unit_sculpts');
    }
};
