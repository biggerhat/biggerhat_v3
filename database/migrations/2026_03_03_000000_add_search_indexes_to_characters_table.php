<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // Equality filters
            $table->index('faction');
            $table->index('second_faction');
            $table->index('station');
            $table->index('base');
            $table->index('defense_suit');
            $table->index('willpower_suit');

            // Range filters
            $table->index('cost');
            $table->index('health');
            $table->index('speed');
            $table->index('defense');
            $table->index('willpower');
            $table->index('size');

            // Boolean filters
            $table->index('generates_stone');
            $table->index('is_unhirable');
            $table->index('is_beta');

            // Sort default
            $table->index('display_name');
        });

        Schema::table('miniatures', function (Blueprint $table) {
            // Composite index for standardMiniatures scope (whereHas)
            $table->index(['character_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropIndex(['faction']);
            $table->dropIndex(['second_faction']);
            $table->dropIndex(['station']);
            $table->dropIndex(['base']);
            $table->dropIndex(['defense_suit']);
            $table->dropIndex(['willpower_suit']);
            $table->dropIndex(['cost']);
            $table->dropIndex(['health']);
            $table->dropIndex(['speed']);
            $table->dropIndex(['defense']);
            $table->dropIndex(['willpower']);
            $table->dropIndex(['size']);
            $table->dropIndex(['generates_stone']);
            $table->dropIndex(['is_unhirable']);
            $table->dropIndex(['is_beta']);
            $table->dropIndex(['display_name']);
        });

        Schema::table('miniatures', function (Blueprint $table) {
            $table->dropIndex(['character_id', 'version']);
        });
    }
};
