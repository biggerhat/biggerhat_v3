<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->json('custom_references')->nullable()->after('references');
        });
    }

    public function down(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropColumn('custom_references');
        });
    }
};
