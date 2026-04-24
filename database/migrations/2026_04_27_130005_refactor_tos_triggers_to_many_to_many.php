<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires the FK to be dropped before the index it relies
        // on. Do FK first, then unique, then the columns themselves.
        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->dropForeign(['action_id']);
        });

        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->dropUnique(['action_id', 'slug']);
            $table->dropColumn(['action_id', 'sort_order']);
        });

        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->unique('slug');
        });

        // Many-to-many — triggers are commonly shared across multiple
        // actions (e.g. "Critical Strike" may appear on every Melee
        // action). Storing the attachment on a pivot lets one Trigger row
        // back N actions without duplication.
        Schema::create('tos_action_trigger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('tos_actions')->cascadeOnDelete();
            $table->foreignId('trigger_id')->constrained('tos_triggers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['action_id', 'trigger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_action_trigger');

        Schema::table('tos_triggers', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignId('action_id')->nullable()->constrained('tos_actions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['action_id', 'slug']);
        });
    }
};
