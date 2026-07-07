<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * supporter_since is admin-entered (not auto-now()) so real Ko-fi donors
     * predating this feature can be backfilled with an accurate date.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('supporter_since')->nullable()->after('collection_is_public');
            $table->boolean('show_on_supporters_page')->default(false)->after('supporter_since');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['supporter_since', 'show_on_supporters_page']);
        });
    }
};
