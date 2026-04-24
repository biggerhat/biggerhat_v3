<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_allegiance_unit', function (Blueprint $table) {
            $table->foreignId('allegiance_id')->constrained('tos_allegiances')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('tos_units')->cascadeOnDelete();

            $table->primary(['allegiance_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_allegiance_unit');
    }
};
