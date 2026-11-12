<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // When on, signing up adds the person to the player list straight away (while a seat is free) instead of waiting for the organizer.
            $table->boolean('instant_signup')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('instant_signup');
        });
    }
};
