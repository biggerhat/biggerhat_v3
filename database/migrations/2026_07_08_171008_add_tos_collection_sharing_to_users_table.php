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
        Schema::table('users', function (Blueprint $table) {
            $table->string('tos_collection_share_code', 12)->nullable()->unique()->after('collection_is_public');
            $table->boolean('tos_collection_is_public')->default(false)->after('tos_collection_share_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tos_collection_share_code', 'tos_collection_is_public']);
        });
    }
};
