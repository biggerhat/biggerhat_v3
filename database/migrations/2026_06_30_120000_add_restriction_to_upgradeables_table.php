<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upgradeables', function (Blueprint $table) {
            $table->string('restriction')->nullable()->after('is_signature_action');
        });
    }

    public function down(): void
    {
        Schema::table('upgradeables', function (Blueprint $table) {
            $table->dropColumn('restriction');
        });
    }
};
