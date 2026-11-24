<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battle_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The game it is about (a tracker game or a logged one). The report outlives the game if the game is deleted.
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            // Or a tournament round the writer played that was not tracked in the Game Tracker (a report has a game or a round, not both).
            $table->foreignId('tournament_game_id')->nullable()->constrained()->nullOnDelete();
            // The crew list the writer played: shown under the scoreboard (the master and models only, never the crew's own name or notes).
            $table->foreignId('crew_build_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->json('content')->nullable();
            // Whether the scoreboard of the linked game sits above the text.
            $table->boolean('show_game')->default(true);
            // Whether readers may comment once it is public. The writer polices their own thread.
            $table->boolean('allow_comments')->default(true);
            // Public only once a moderator approved it: private -> pending (asked) -> approved | rejected.
            $table->string('status', 12)->default('private');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_note', 500)->nullable();
            // What a moderator read when they approved it (see Support\BattleReports\ApprovedSnapshot): later edits are measured against it.
            $table->json('approved_snapshot')->nullable();
            // The writer's "please look again" after a report was turned down. Set once per turn-down; an edit clears it.
            $table->string('appeal_note', 500)->nullable();
            // When it was approved: the day it went public.
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'game_id'], 'battle_reports_user_game_unique');
            $table->unique(['user_id', 'tournament_game_id'], 'battle_reports_user_round_unique');
            $table->index(['status', 'published_at'], 'battle_reports_status_index');
        });

        // Every moderator decision, kept after the report is deleted: the record behind a writer's trust (see Support\BattleReports\TrustedAuthors).
        Schema::create('battle_report_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the writer
            $table->foreignId('battle_report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->string('decision', 12); // approved | rejected
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete(); // null: approved automatically
            $table->string('note', 500)->nullable();
            // A turn-down a moderator later reversed on appeal no longer counts against the writer.
            $table->timestamp('overturned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'decision'], 'battle_report_decisions_user_index');
        });

        Schema::create('battle_report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 1000);
            $table->timestamps();

            $table->index(['battle_report_id', 'id'], 'battle_report_comments_report_index');
        });

        Schema::create('battle_report_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['battle_report_id', 'user_id'], 'battle_report_reactions_unique');
        });

        // A reader's report of a public battle report. One per reader per report; a moderator reads them and dismisses or takes the report down.
        Schema::create('battle_report_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 300);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['battle_report_id', 'user_id'], 'battle_report_flags_unique');
            $table->index(['battle_report_id', 'resolved_at'], 'battle_report_flags_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_report_reactions');
        Schema::dropIfExists('battle_report_comments');
        Schema::dropIfExists('battle_report_decisions');
        Schema::dropIfExists('battle_report_flags');
        Schema::dropIfExists('battle_reports');
    }
};
