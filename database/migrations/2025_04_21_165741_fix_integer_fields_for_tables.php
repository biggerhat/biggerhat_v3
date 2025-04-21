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
            $table->string('range')->nullable()->change();
            $table->string('target_number')->nullable()->change();
            $table->string('stat')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->integer('range')->nullable()->change();
            $table->integer('target_number')->nullable()->change();
            $table->integer('stat')->nullable()->change();
        });
    }
};
