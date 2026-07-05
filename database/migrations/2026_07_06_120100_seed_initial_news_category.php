<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds one working news category so /news has somewhere to post to in
     * every environment without depending on db:seed being re-run.
     */
    public function up(): void
    {
        DB::table('blog_categories')->insertOrIgnore([
            'name' => 'Site Updates',
            'slug' => 'site-updates',
            'description' => 'Official site updates, how-tos, and announcements.',
            'is_news' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Only remove the seeded row if nothing has been posted to it —
        // never destroy real news content on rollback.
        $category = DB::table('blog_categories')->where('slug', 'site-updates')->first();
        if (! $category) {
            return;
        }

        $hasPosts = DB::table('blog_posts')->where('blog_category_id', $category->id)->exists();
        if (! $hasPosts) {
            DB::table('blog_categories')->where('id', $category->id)->delete();
        }
    }
};
