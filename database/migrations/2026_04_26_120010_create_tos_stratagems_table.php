<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_stratagems', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // Stratagems may be keyed to a specific Allegiance OR to an
            // Allegiance Type (rulebook p. 13: "...must match the Allegiance
            // OR Allegiance Type..."). If both are null the stratagem is
            // treated as universal.
            $table->foreignId('allegiance_id')->nullable()->constrained('tos_allegiances')->nullOnDelete();
            $table->string('allegiance_type')->nullable();
            // Tactical Cost — paid in Tactics Tokens on draw (rulebook p. 13),
            // NOT Scrip.
            $table->unsignedSmallInteger('tactical_cost')->default(1);
            $table->longText('effect')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['allegiance_id', 'allegiance_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_stratagems');
    }
};
