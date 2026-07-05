<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Site News (pg N/A — site feature, not a rulebook one) piggybacks on the
     * Blog/Article model: any blog_category flagged is_news is pooled onto
     * the /news page and excluded from the normal /blog listing.
     */
    public function up(): void
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->boolean('is_news')->default(false)->after('description');
            $table->index('is_news');
        });
    }

    public function down(): void
    {
        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropIndex(['is_news']);
            $table->dropColumn('is_news');
        });
    }
};
