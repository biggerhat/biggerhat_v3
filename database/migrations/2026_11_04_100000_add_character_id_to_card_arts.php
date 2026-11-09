<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Optional link from a piece of art to the official character it depicts, used to suggest it in the card editor. */
    public function up(): void
    {
        Schema::table('card_arts', function (Blueprint $table) {
            $table->foreignId('character_id')->nullable()->after('category')->constrained('characters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('card_arts', function (Blueprint $table) {
            $table->dropForeignSafe('card_arts', ['character_id']);
            $table->dropColumn('character_id');
        });
    }
};
