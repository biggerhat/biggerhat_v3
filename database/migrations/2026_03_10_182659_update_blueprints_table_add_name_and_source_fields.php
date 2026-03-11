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
        Schema::table('blueprints', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('slug')->after('name');
            $table->string('image')->nullable()->change();
            $table->json('images')->nullable()->after('image');
            $table->string('source_url')->nullable()->after('images');
            $table->string('wyrd_post_slug')->nullable()->unique()->after('source_url');
            $table->date('published_at')->nullable()->after('sculpt_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['name', 'slug', 'images', 'source_url', 'wyrd_post_slug', 'published_at']);
            $table->string('image')->nullable(false)->change();
        });
    }
};
