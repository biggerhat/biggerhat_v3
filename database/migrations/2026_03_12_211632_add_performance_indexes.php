<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->index('is_hidden');
        });

        Schema::table('crew_builds', function (Blueprint $table) {
            $table->index(['is_public', 'is_archived', 'created_at']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['is_hidden']);
        });

        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'is_archived', 'created_at']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
        });
    }
};
