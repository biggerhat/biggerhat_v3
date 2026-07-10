<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a catalog Crew Card effect to the master it's actually printed on
 * (nullable — some rows are generic/unassigned pending admin backfill). Lets
 * the Tier-4 "Crew Card Effect" advancement (pg 32, 54) cascade Master →
 * that master's own crew card, instead of trusting an unrelated free-text
 * "borrowed from" pick alongside an unconstrained flat row list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->foreignId('master_id')->nullable()->after('description')
                ->constrained('characters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_id');
        });
    }
};
