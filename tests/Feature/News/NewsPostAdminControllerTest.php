<?php

use App\Enums\PermissionEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::ManageNews->value, 'guard_name' => 'web']);
    foreach ([PermissionEnum::CreatePosts, PermissionEnum::EditPosts] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->newsEditor = User::factory()->create();
    $this->newsEditor->givePermissionTo(PermissionEnum::ManageNews->value);

    $this->contentCreator = User::factory()->create();
    $this->contentCreator->givePermissionTo([PermissionEnum::CreatePosts->value, PermissionEnum::EditPosts->value]);
});

it('index only returns news posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);
    BlogPost::factory()->create(['blog_category_id' => $articleCategory->id]);

    $this->actingAs($this->newsEditor)
        ->get(route('admin.news.posts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $newsPost->id));
});

it('every admin.news.posts route is forbidden without manage_news, even with create_posts|edit_posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);

    $this->actingAs($this->contentCreator)->get(route('admin.news.posts.index'))->assertForbidden();
    $this->actingAs($this->contentCreator)->get(route('admin.news.posts.create'))->assertForbidden();
    $this->actingAs($this->contentCreator)->get(route('admin.news.posts.edit', $newsPost->slug))->assertForbidden();
    $this->actingAs($this->contentCreator)->post(route('admin.news.posts.store'), ['title' => 'X', 'status' => 'draft'])->assertForbidden();
});

it('store creates a news post assigned to a news category', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.posts.store'), [
            'title' => 'Site Update',
            'status' => 'draft',
            'blog_category_id' => $newsCategory->id,
        ])
        ->assertRedirect(route('admin.news.posts.index'));

    expect(BlogPost::where('title', 'Site Update')->where('user_id', $this->newsEditor->id)->exists())->toBeTrue();
});

it('store rejects assigning a non-news category to a news post', function () {
    $articleCategory = BlogCategory::factory()->create();

    $this->actingAs($this->newsEditor)
        ->postJson(route('admin.news.posts.store'), [
            'title' => 'X',
            'status' => 'draft',
            'blog_category_id' => $articleCategory->id,
        ])
        ->assertStatus(422);
});

it('manage_news alone is enough to publish immediately (no separate publish permission)', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.posts.store'), [
            'title' => 'Published Update',
            'status' => 'published',
            'blog_category_id' => $newsCategory->id,
        ]);

    expect(BlogPost::where('title', 'Published Update')->first()->status->value)->toBe('published');
});

it('edit/update/delete 404 a regular blog post reached via a News admin URL', function () {
    $articleCategory = BlogCategory::factory()->create();
    $articlePost = BlogPost::factory()->create(['blog_category_id' => $articleCategory->id]);

    $this->actingAs($this->newsEditor)->get(route('admin.news.posts.edit', $articlePost->slug))->assertNotFound();
    $this->actingAs($this->newsEditor)->get(route('admin.news.posts.preview', $articlePost->slug))->assertNotFound();
    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.posts.update', $articlePost->slug), ['title' => 'X', 'status' => 'draft'])
        ->assertNotFound();
    $this->actingAs($this->newsEditor)->post(route('admin.news.posts.delete', $articlePost->slug))->assertNotFound();
});

it('delete removes a news post', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $post = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);

    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.posts.delete', $post->slug))
        ->assertRedirect(route('admin.news.posts.index'));

    expect(BlogPost::find($post->id))->toBeNull();
});
