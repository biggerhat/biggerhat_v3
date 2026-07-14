<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lucky_miss_catalog', function (Blueprint $table) {
            // Nullable — some Lucky Miss rows are bespoke text with no real
            // Ability to link (mirrors AdvancementAbility::ability_id).
            $table->foreignId('ability_id')->nullable()->after('body')->constrained('abilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lucky_miss_catalog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ability_id');
        });
    }
};
