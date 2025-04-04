<?php

use App\Enums\EditionEnum;
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
            $table->string('card_front');
            $table->string('card_back');
            $table->string('edition')->default(EditionEnum::FourthEdition->value);
            $table->timestamps();
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
