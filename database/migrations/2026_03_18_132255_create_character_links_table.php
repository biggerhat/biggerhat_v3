<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('linked_character_id')->constrained('characters')->cascadeOnDelete();
            $table->string('type'); // 'summons', 'replaces_into'
            $table->timestamps();

            $table->unique(['character_id', 'linked_character_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_links');
    }
};
