<?php

use App\Enums\RoleEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Spatie\Permission\Models\Role;

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

it('view marks the author as a supporter when they hold the role, and not otherwise', function () {
    Role::firstOrCreate(['name' => RoleEnum::Supporter->value, 'guard_name' => 'web']);
    $newsCategory = BlogCategory::factory()->news()->create();

    $supporterAuthor = User::factory()->create();
    $supporterAuthor->assignRole(RoleEnum::Supporter->value);
    $supporterPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id, 'user_id' => $supporterAuthor->id]);

    $this->get(route('news.view', $supporterPost->slug))
        ->assertInertia(fn ($page) => $page->where('post.author.is_supporter', true));

    $regularAuthor = User::factory()->create();
    $regularPost = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id, 'user_id' => $regularAuthor->id]);

    $this->get(route('news.view', $regularPost->slug))
        ->assertInertia(fn ($page) => $page->where('post.author.is_supporter', false));
});

it('view does not crash building the SEO description when a post has no excerpt', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $newsCategory->id, 'excerpt' => null]);

    $this->get(route('news.view', $post->slug))->assertOk();
});
