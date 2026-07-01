<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->boolean('is_signature_action')->default(false)->after('action_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_crew_card_actions', function (Blueprint $table) {
            $table->dropColumn('is_signature_action');
        });
    }
};
