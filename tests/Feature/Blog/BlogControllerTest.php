<?php

use App\Enums\RoleEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Spatie\Permission\Models\Role;

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

it('view marks the author as a supporter when they hold the role, and not otherwise', function () {
    Role::firstOrCreate(['name' => RoleEnum::Supporter->value, 'guard_name' => 'web']);
    $articleCategory = BlogCategory::factory()->create();

    $supporterAuthor = User::factory()->create();
    $supporterAuthor->assignRole(RoleEnum::Supporter->value);
    $supporterPost = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id, 'user_id' => $supporterAuthor->id]);

    $this->get(route('blog.view', $supporterPost->slug))
        ->assertInertia(fn ($page) => $page->where('post.author.is_supporter', true));

    $regularAuthor = User::factory()->create();
    $regularPost = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id, 'user_id' => $regularAuthor->id]);

    $this->get(route('blog.view', $regularPost->slug))
        ->assertInertia(fn ($page) => $page->where('post.author.is_supporter', false));
});

it('view does not crash building the SEO description when a post has no excerpt', function () {
    $articleCategory = BlogCategory::factory()->create();
    $post = BlogPost::factory()->published()->create(['blog_category_id' => $articleCategory->id, 'excerpt' => null]);

    $this->get(route('blog.view', $post->slug))->assertOk();
});
