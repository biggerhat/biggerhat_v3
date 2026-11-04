<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Small WebP of the card front for gallery tiles, so My Cards doesn't download the full-size render per card. */
    public function up(): void
    {
        foreach (['custom_characters', 'custom_upgrades'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('thumb_image')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['custom_characters', 'custom_upgrades'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('thumb_image');
            });
        }
    }
};
