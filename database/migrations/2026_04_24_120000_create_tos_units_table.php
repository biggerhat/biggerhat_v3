<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_units', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('title')->nullable();
            // Scrip — positive when the unit COSTS scrip to hire; for Commanders
            // (where a unit PROVIDES scrip to its Company per rulebook p. 9),
            // we still store the magnitude here and rely on the Commander
            // Special Unit Rule to flip semantics in display + builder code.
            $table->integer('scrip')->default(0);
            // Tactics — number of Tactics Tokens generated on Activation
            // (rulebook p. 9). Nullable because not all units have a Tactics
            // value; small string to allow `X` / `*` if needed later.
            $table->string('tactics', 8)->nullable();
            $table->longText('description')->nullable();
            $table->longText('lore_text')->nullable();
            // Neutral-side hireability — when set and the unit has no
            // Allegiance attachments (or alongside them), the unit is
            // hireable by ANY Allegiance of the matching type (rulebook
            // "Neutral (Earth)" / "Neutral (Malifaux)" pool).
            $table->string('restriction', 16)->nullable();
            // Self-FK for Combined Arms — points to the embedded child Unit
            // Card (rulebook p. 11). Nullable; set on parent Unit only.
            $table->foreignId('combined_arms_child_id')->nullable()->constrained('tos_units')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_units');
    }
};
