<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advancement_actions', function (Blueprint $table) {
            // Bespoke rows only (pg 31): whether taking this Action
            // Advancement grants a Signature Action. Lookup rows instead
            // inherit the linked Action's own is_signature flag.
            $table->boolean('is_signature')->default(false)->after('is_always_available');
        });
    }

    public function down(): void
    {
        Schema::table('advancement_actions', function (Blueprint $table) {
            $table->dropColumn('is_signature');
        });
    }
};
