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
        Schema::table('packageables', function (Blueprint $table) {
            $table->unsignedSmallInteger('quantity')->default(1)->after('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packageables', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
