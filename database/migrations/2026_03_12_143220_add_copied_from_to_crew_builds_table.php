<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->foreignId('copied_from_id')->nullable()->after('user_id')->constrained('crew_builds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crew_builds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_id');
        });
    }
};
