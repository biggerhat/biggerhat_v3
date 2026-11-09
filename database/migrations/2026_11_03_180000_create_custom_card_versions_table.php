<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Previous states of a custom character / upgrade, kept per card so an edit can be undone from the editor. */
    public function up(): void
    {
        Schema::create('custom_card_versions', function (Blueprint $table) {
            $table->id();
            $table->morphs('versionable', 'custom_card_versions_card_index');
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_card_versions');
    }
};
