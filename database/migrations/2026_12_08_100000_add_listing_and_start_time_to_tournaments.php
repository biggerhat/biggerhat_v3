<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Unlisted events stay reachable by link but skip the public list, search and sitemap (a private league night).
            $table->boolean('is_unlisted')->default(false);
            // Local wall-clock start ("10:00") and an IANA zone name; the date alone stays valid when these are empty.
            $table->string('start_time', 5)->nullable();
            $table->string('timezone', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['is_unlisted', 'start_time', 'timezone']);
        });
    }
};
