<?php

use App\Models\BlogCategory;
use App\Models\BlogPost;

it('index excludes news posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Blog/Index')
            ->where('posts.data.0.id', $articlePost->id)
            ->has('posts.data', 1)
        );

    expect($newsPost->id)->not->toBe($articlePost->id);
});

it('index excludes news categories from the category tabs', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Blog/Index')
            ->has('categories', 1)
            ->where('categories.0.id', $articleCategory->id)
        );

    expect($newsCategory->is_news)->toBeTrue();
});

it('view 404s for a post whose category is flagged is_news', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $newsPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id]);

    $this->get(route('blog.view', $newsPost->slug))->assertNotFound();
});

it('view renders a regular published article', function () {
    $articleCategory = BlogCategory::factory()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id]);

    $this->get(route('blog.view', $post->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Blog/View')->where('post.id', $post->id));
});

it('view does not crash building the SEO description when a post has no excerpt', function () {
    $articleCategory = BlogCategory::factory()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id, 'excerpt' => null]);

    $this->get(route('blog.view', $post->slug))->assertOk();
});
