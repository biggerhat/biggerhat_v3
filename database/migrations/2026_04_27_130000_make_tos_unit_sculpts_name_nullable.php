<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_unit_sculpts', function (Blueprint $table) {
            // Many units ship with a single unnamed sculpt (default art);
            // admin UIs fall back to the parent unit's name + sculpt index.
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tos_unit_sculpts', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }
};
