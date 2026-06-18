<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Totem templates don't carry a faction (the totem inherits the leader's
 * keywords/faction when it's added to a crew, pg 52) and may leave base size
 * unset until the player picks the model. Relax both columns to nullable so a
 * template can be saved without them; base still defaults to '30' at the DB
 * level for rows that don't specify one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->string('faction')->nullable()->change();
            $table->string('base')->nullable()->default('30')->change();
        });
    }

    public function down(): void
    {
        Schema::table('custom_characters', function (Blueprint $table) {
            $table->string('faction')->nullable(false)->change();
            $table->string('base')->nullable(false)->default('30')->change();
        });
    }
};
