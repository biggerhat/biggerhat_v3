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
        Schema::table('tos_assets', function (Blueprint $table) {
            // Disabled-side art for the asset card (flips to this in the UI).
            $table->string('back_image_path')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tos_assets', function (Blueprint $table) {
            $table->dropColumn('back_image_path');
        });
    }
};
