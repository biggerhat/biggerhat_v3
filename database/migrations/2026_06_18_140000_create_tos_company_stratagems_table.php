<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Company's Stratagem Deck — the Stratagems it takes to the table (General +
 * Primary Allegiance + up to two Envoy, rulebook p. 13/30). Mirrors the
 * Garrison stratagem pool pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tos_company_stratagems', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('tos_companies')->cascadeOnDelete();
            $table->foreignId('stratagem_id')->constrained('tos_stratagems')->cascadeOnDelete();
            $table->primary(['company_id', 'stratagem_id'], 'tos_company_stratagems_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_company_stratagems');
    }
};
