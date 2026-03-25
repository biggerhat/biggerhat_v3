<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_links', function (Blueprint $table) {
            $table->unsignedTinyInteger('count')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('character_links', function (Blueprint $table) {
            $table->dropColumn('count');
        });
    }
};
