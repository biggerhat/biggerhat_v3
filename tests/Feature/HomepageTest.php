<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;

it('recent_articles excludes news posts and recent_news includes only news posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recent_articles', 1)
            ->where('recent_articles.0.slug', $articlePost->slug)
            ->has('recent_news', 1)
            ->where('recent_news.0.slug', $newsPost->slug)
        );
});

it('recent_articles and recent_news are each capped at 4', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    BlogPost::factory()->published()->count(5)->create(['blog_category_id' => $newsCategory->id]);
    BlogPost::factory()->published()->count(5)->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('recent_articles', 4)->has('recent_news', 4));
});
