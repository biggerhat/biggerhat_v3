<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;

it('index only returns news-flagged published posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);
    BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('News/Index')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $newsPost->id)
        );
});

it('index filters by category slug', function () {
    $categoryA = BlogCategory::factory()->news()->create();
    $categoryB = BlogCategory::factory()->news()->create();

    $postA = BlogPost::factory()->published()->create(['blog_category_id' => $categoryA->id]);
    BlogPost::factory()->published()->create(['blog_category_id' => $categoryB->id]);

    $this->get(route('news.index', ['category' => $categoryA->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('posts.data', 1)->where('posts.data.0.id', $postA->id));
});

it('view 404s for a post whose category is not news', function () {
    $articleCategory = BlogCategory::factory()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('news.view', $post->slug))->assertNotFound();
});

it('view 404s for an unpublished news post', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $post = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]); // draft by default

    $this->get(route('news.view', $post->slug))->assertNotFound();
});

it('view renders a published news post', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);

    $this->get(route('news.view', $post->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('News/View')->where('post.id', $post->id));
});

it('view does not crash building the SEO description when a post has no excerpt', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id, 'excerpt' => null]);

    $this->get(route('news.view', $post->slug))->assertOk();
});
