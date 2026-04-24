<?php

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_allegiance_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            // Allegiance Type per rulebook p. 8 (Earth or Malifaux). Mirrors the
            // parent Allegiance's type by default but stored here so a card can
            // diverge (e.g. hybrid-allegiance expansions).
            $table->string('type')->default(AllegianceTypeEnum::Earth->value);
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tos_allegiance_card_ability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allegiance_card_id')->constrained('tos_allegiance_cards')->cascadeOnDelete();
            $table->foreignId('ability_id')->constrained('tos_abilities')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['allegiance_card_id', 'ability_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_allegiance_card_ability');
        Schema::dropIfExists('tos_allegiance_cards');
    }
};
