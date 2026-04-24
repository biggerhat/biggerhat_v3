<?php

use App\Enums\TOS\UnitSideEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_unit_sides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();
            // Standard side has the art; Glory side is the "pushed limits"
            // power-up state. AVs and Ability/Action lists differ between
            // sides (rulebook p. 9).
            $table->string('side')->default(UnitSideEnum::Standard->value);
            // Acting Values (Sp/Df/Wp/Ar). Required positive integers.
            $table->unsignedTinyInteger('speed');
            $table->unsignedTinyInteger('defense');
            $table->unsignedTinyInteger('willpower');
            $table->unsignedTinyInteger('armor');
            $table->timestamps();

            $table->unique(['unit_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_unit_sides');
    }
};
