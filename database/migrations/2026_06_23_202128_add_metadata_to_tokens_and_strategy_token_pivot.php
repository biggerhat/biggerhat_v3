<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokens', function (Blueprint $table) {
            // When the token auto-removes (TokenRemovalTimingEnum); null = manual/persistent.
            $table->string('removal_timing')->nullable()->after('description');
            // "General" tokens (Focus, Shielded, Impact, …) surface in every
            // crew's tracker references regardless of which models grant them.
            $table->boolean('is_general')->default(false)->index()->after('removal_timing');
        });

        Schema::create('strategy_token', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategy_id')->constrained('strategies')->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('tokens')->cascadeOnDelete();
            $table->unique(['strategy_id', 'token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_token');

        Schema::table('tokens', function (Blueprint $table) {
            $table->dropColumn(['removal_timing', 'is_general']);
        });
    }
};
