<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Key into config('tournaments.rulesets'). Existing rows are all Malifaux Gaining Grounds.
            $table->string('ruleset', 64)->default('malifaux_gaining_grounds')->after('name');
            // Per-tournament overrides layered over the ruleset's scoring parameters.
            $table->json('scoring_config')->nullable();
        });

        // Bye points become overrides: null = use the ruleset's values. Existing rows keep
        // their stored values, which equal the Gaining Grounds defaults.
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('bye_tp')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('bye_diff')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('bye_vp')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedTinyInteger('bye_tp')->default(3)->nullable(false)->change();
            $table->unsignedTinyInteger('bye_diff')->default(4)->nullable(false)->change();
            $table->unsignedTinyInteger('bye_vp')->default(6)->nullable(false)->change();
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['ruleset', 'scoring_config']);
        });
    }
};
