<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('upgrades', function (Blueprint $table) {
            $table->dropForeignSafe('upgrades_master_id_foreign');
        });

        Schema::table('upgrades', function (Blueprint $table) {
            $table->after('description', function ($table) {
                $table->foreignId('master_id')->nullable()->change()->constrained('characters')->cascadeOnDelete();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
    }
};
