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
        Schema::table('actions', function (Blueprint $table) {
            $table->after('cost', function ($table) {
                $table->boolean('costs_stone')->default(false);
                $table->string('slug')->unique()->change();
                $table->dropColumn('cost');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn('costs_stone');
            $table->string('slug')->change();
            $table->string('cost')->nullable();
        });
    }
};
