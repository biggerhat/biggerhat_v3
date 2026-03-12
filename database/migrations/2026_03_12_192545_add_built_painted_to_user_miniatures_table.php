<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_miniatures', function (Blueprint $table) {
            $table->boolean('is_built')->default(false)->after('quantity');
            $table->boolean('is_painted')->default(false)->after('is_built');
        });
    }

    public function down(): void
    {
        Schema::table('user_miniatures', function (Blueprint $table) {
            $table->dropColumn(['is_built', 'is_painted']);
        });
    }
};
