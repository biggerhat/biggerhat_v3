<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // A short organizer notice shown on the public page and display mode ("Lunch until 1:15", "Table 4 moved").
            $table->string('announcement', 500)->nullable();
            $table->timestamp('announced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['announcement', 'announced_at']);
        });
    }
};
