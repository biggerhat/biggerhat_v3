<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('collection_share_code', 12)->nullable()->unique()->after('remember_token');
            $table->boolean('collection_is_public')->default(false)->after('collection_share_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['collection_share_code', 'collection_is_public']);
        });
    }
};
