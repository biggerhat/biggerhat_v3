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
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            // Binary pending↔accepted state — nullable timestamp, this
            // codebase's established convention (see CampaignInvitation.accepted_at)
            // rather than a string status column (those are reserved for
            // 3+-state lifecycles elsewhere in this app). Declining a
            // request or unfriending just deletes the row — no separate
            // "declined" state needed.
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            // No DB-level uniqueness across the pair — a single unique index
            // can't express "no row in either direction" for a
            // self-referential relationship. Deduped app-side in
            // FriendshipController::store().
            $table->index(['requester_id', 'addressee_id']);
            $table->index(['addressee_id', 'requester_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
