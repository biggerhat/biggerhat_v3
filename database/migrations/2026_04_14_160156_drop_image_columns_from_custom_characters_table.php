<?php

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
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->dropColumn(['character_image', 'front_image', 'back_image', 'combo_image']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->string('character_image')->nullable()->after('is_unhirable');
            $table->string('front_image')->nullable()->after('character_image');
            $table->string('back_image')->nullable()->after('front_image');
            $table->string('combo_image')->nullable()->after('back_image');
        });
    }
};
