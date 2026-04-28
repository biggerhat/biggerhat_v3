<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per the rulebook, some Units flip their Tactics value when they reach
 * Glory. Storing the Glory-side value as a separate nullable column keeps the
 * Standard `tactics` field as the single source of truth (most units have the
 * same value on both sides), and the model's accessor falls back to it when
 * `glory_tactics` is null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_units', function (Blueprint $table) {
            $table->string('glory_tactics', 8)->nullable()->after('tactics');
        });
    }

    public function down(): void
    {
        Schema::table('tos_units', function (Blueprint $table) {
            $table->dropColumn('glory_tactics');
        });
    }
};
