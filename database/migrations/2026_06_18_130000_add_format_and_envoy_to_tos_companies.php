<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the Company builder format + Envoy aware (June 2026 errata): a Company
 * picks a play format (game size) and may take a second Allegiance as an Envoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->string('format')->nullable()->after('allegiance_id');
            $table->foreignId('envoy_allegiance_id')->nullable()->after('format')
                ->constrained('tos_allegiances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->dropForeignSafe(['envoy_allegiance_id']);
            $table->dropColumn(['format', 'envoy_allegiance_id']);
        });
    }
};
