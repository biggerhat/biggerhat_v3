<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master switch for mobile push (the in-app bell is unaffected). Only matters once a device token exists. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('push_notifications')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_notifications');
        });
    }
};
