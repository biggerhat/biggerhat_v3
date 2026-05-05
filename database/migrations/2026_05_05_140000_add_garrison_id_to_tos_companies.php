<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional link from a Company to a Garrison. When set, the Company Builder
 * restricts its hiring pool to the Garrison's declared Units + Assets — the
 * round-by-round flow tournament players use to build their game-day list
 * out of their declared tournament pool. NULL means casual/unrestricted
 * play (the Builder behaves exactly as it did before).
 *
 * The FK is `nullOnDelete` so deleting a Garrison degrades affected
 * Companies to the unrestricted pool rather than cascading them away —
 * the Company is still a valid roster, it just stops being tied to a
 * specific Garrison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->foreignId('garrison_id')->nullable()->after('allegiance_id')
                ->constrained('tos_garrisons')->nullOnDelete();
            $table->index('garrison_id');
        });
    }

    public function down(): void
    {
        Schema::table('tos_companies', function (Blueprint $table) {
            $table->dropForeign(['garrison_id']);
            $table->dropIndex(['garrison_id']);
            $table->dropColumn('garrison_id');
        });
    }
};
