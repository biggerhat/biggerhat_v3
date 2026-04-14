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
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->json('content_blocks')->nullable()->after('keyword_name');
            $table->json('back_markers')->nullable()->after('back_tokens');
            $table->dropColumn(['abilities', 'actions', 'back_actions', 'restrictions']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_upgrades', function (Blueprint $table) {
            $table->dropColumn(['content_blocks', 'back_markers']);
            $table->json('abilities')->nullable();
            $table->json('actions')->nullable();
            $table->json('back_actions')->nullable();
            $table->text('restrictions')->nullable();
        });
    }
};
