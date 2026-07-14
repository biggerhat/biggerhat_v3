<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tier-4 Crew Card Advancement (pg 32, 54) may not borrow an effect that
     * "references a power bar or causes the crew card to be swapped with a
     * different crew card." Nullable — null means eligible for borrowing.
     * Lives on the general Crew Card Upgrade catalog's shared upgradeables
     * pivot (actions/abilities/triggers), alongside the existing `restriction`
     * column, so admins can flag which effects on a normal Crew Upgrade may
     * not be borrowed.
     */
    public function up(): void
    {
        Schema::table('upgradeables', function (Blueprint $table) {
            $table->string('borrow_exclusion')->nullable()->after('restriction');
        });
    }

    public function down(): void
    {
        Schema::table('upgradeables', function (Blueprint $table) {
            $table->dropColumn('borrow_exclusion');
        });
    }
};
