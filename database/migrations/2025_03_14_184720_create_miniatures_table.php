<?php

use App\Enums\SculptVersionEnum;
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
        Schema::create('miniatures', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('display_name');
            $table->string('slug');
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            $table->string('combination_image')->nullable();
            $table->string('version')->default(SculptVersionEnum::FourthEdition->value);
            $table->timestamps();
        });

        Schema::create('miniatureables', function (Blueprint $table) {
            $table->morphs('miniatureable');
            $table->foreignId('miniature_id')->constrained('miniatures')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('miniatures');
    }
};
