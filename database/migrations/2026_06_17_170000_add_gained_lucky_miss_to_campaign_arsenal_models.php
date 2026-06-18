<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Red-joker injury / doctor results send a model to the Lucky Miss table
 * (pg 36) where it gains a permanent beneficial upgrade. Recorded as a list of
 * lucky_miss_catalog ids on the model, mirroring the gained_characteristics
 * JSON column added alongside the Back-Alley Doctor work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->json('gained_lucky_miss_ids')->nullable()->after('gained_characteristics');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_arsenal_models', function (Blueprint $table) {
            $table->dropColumn('gained_lucky_miss_ids');
        });
    }
};
