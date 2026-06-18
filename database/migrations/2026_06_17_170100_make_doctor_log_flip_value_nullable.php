<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Red-joker Back-Alley Doctor flips have no numeric value (they resolve to the
 * dedicated Red Joker row), so the aftermath log's flip_value must allow null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedTinyInteger('flip_value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_aftermath_doctor', function (Blueprint $table) {
            $table->unsignedTinyInteger('flip_value')->nullable(false)->change();
        });
    }
};
