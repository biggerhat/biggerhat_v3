<?php

use App\Enums\TOS\ActionTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_actions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default(ActionTypeEnum::Melee->value);
            // Action header data per rulebook p. 22:
            //   `(AV v Stat | Range)` for opposed-duel actions,
            //   `(Range)` for non-duel actions.
            $table->unsignedTinyInteger('av')->nullable();              // 4 in "4 v Df"
            $table->string('av_target', 8)->nullable();                 // "Df", "Wp" — null for simple-duel/non-duel
            $table->unsignedTinyInteger('tn')->nullable();              // target number for simple duels
            $table->string('range', 32)->nullable();                    // "6\"", "y" (engaged), "self"
            $table->string('damage', 32)->nullable();                   // "1/2/3" or "Strength 2"
            $table->longText('body')->nullable();                       // full effect text
            $table->timestamps();
        });

        Schema::create('tos_unit_side_action', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_side_id')->constrained('tos_unit_sides')->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['unit_side_id', 'action_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_unit_side_action');
        Schema::dropIfExists('tos_actions');
    }
};
