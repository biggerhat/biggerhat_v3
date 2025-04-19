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
        Schema::table('triggers', function (Blueprint $table) {
            $table->after('slug', function ($table) {
                $table->boolean('costs_stone')->default(false);
                $table->string('suits')->nullable()->change();
                $table->longText('description')->nullable()->change();
                $table->longText('internal_notes')->nullable()->change();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropColumn('costs_stone');
            $table->string('suits')->change();
        });
    }
};
