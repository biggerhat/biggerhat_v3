<?php

use App\Enums\PermissionEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        PermissionEnum::CreatePosts,
        PermissionEnum::EditPosts,
        PermissionEnum::PublishPosts,
        PermissionEnum::DeletePosts,
        PermissionEnum::ManageAllPosts,
        PermissionEnum::ManageNews,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->author = User::factory()->create();
    $this->author->givePermissionTo([PermissionEnum::CreatePosts->value, PermissionEnum::EditPosts->value]);

    $this->stranger = User::factory()->create();
});

it('index only returns the author\'s own posts without manage_all_posts', function () {
    $mine = BlogPost::factory()->create(['user_id' => $this->author->id]);
    BlogPost::factory()->create(); // someone else's

    $this->actingAs($this->author)
        ->get(route('admin.blog.posts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $mine->id));
});

it('index excludes news posts even for a manage_all_posts holder', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo([PermissionEnum::EditPosts->value, PermissionEnum::ManageAllPosts->value]);

    $newsCategory = BlogCategory::factory()->news()->create();
    BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);
    $articlePost = BlogPost::factory()->create();

    $this->actingAs($manager)
        ->get(route('admin.blog.posts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('posts', 1)->where('posts.0.id', $articlePost->id));
});

it('store creates a post for the authenticated author', function () {
    $this->actingAs($this->author)
        ->post(route('admin.blog.posts.store'), [
            'title' => 'My Post',
            'status' => 'draft',
        ])
        ->assertRedirect(route('admin.blog.posts.index'));

    expect(BlogPost::where('title', 'My Post')->where('user_id', $this->author->id)->exists())->toBeTrue();
});

it('store rejects a user without create_posts', function () {
    $this->actingAs($this->stranger)
        ->post(route('admin.blog.posts.store'), ['title' => 'X', 'status' => 'draft'])
        ->assertForbidden();
});

it('store rejects assigning a news category to a normal blog post', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $this->actingAs($this->author)
        ->postJson(route('admin.blog.posts.store'), [
            'title' => 'My Post',
            'status' => 'draft',
            'blog_category_id' => $newsCategory->id,
        ])
        ->assertStatus(422);
});

it('store forces a non-publish_posts author back to draft', function () {
    $this->actingAs($this->author)
        ->post(route('admin.blog.posts.store'), [
            'title' => 'Published Attempt',
            'status' => 'published',
        ]);

    expect(BlogPost::where('title', 'Published Attempt')->first()->status->value)->toBe('draft');
});

it('update modifies the author\'s own post', function () {
    $post = BlogPost::factory()->create(['user_id' => $this->author->id]);

    $this->actingAs($this->author)
        ->post(route('admin.blog.posts.update', $post->slug), [
            'title' => 'Updated Title',
            'status' => 'draft',
        ])
        ->assertRedirect(route('admin.blog.posts.index'));

    expect($post->fresh()->title)->toBe('Updated Title');
});

it('update rejects a non-owner without manage_all_posts', function () {
    $post = BlogPost::factory()->create();
    $otherAuthor = User::factory()->create();
    $otherAuthor->givePermissionTo([PermissionEnum::EditPosts->value]);

    $this->actingAs($otherAuthor)
        ->post(route('admin.blog.posts.update', $post->slug), ['title' => 'Hijacked', 'status' => 'draft'])
        ->assertForbidden();
});

it('edit/update/delete/preview 404 a news post reached via a Blog admin URL', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo([PermissionEnum::EditPosts->value, PermissionEnum::ManageAllPosts->value, PermissionEnum::DeletePosts->value]);

    $newsCategory = BlogCategory::factory()->news()->create();
    $newsPost = BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);

    $this->actingAs($manager)->get(route('admin.blog.posts.edit', $newsPost->slug))->assertNotFound();
    $this->actingAs($manager)->get(route('admin.blog.posts.preview', $newsPost->slug))->assertNotFound();
    $this->actingAs($manager)->post(route('admin.blog.posts.update', $newsPost->slug), ['title' => 'X', 'status' => 'draft'])->assertNotFound();
    $this->actingAs($manager)->post(route('admin.blog.posts.delete', $newsPost->slug))->assertNotFound();
});

it('delete removes the author\'s own post', function () {
    $post = BlogPost::factory()->create(['user_id' => $this->author->id]);
    $this->author->givePermissionTo(PermissionEnum::DeletePosts->value);

    $this->actingAs($this->author)
        ->post(route('admin.blog.posts.delete', $post->slug))
        ->assertRedirect(route('admin.blog.posts.index'));

    expect(BlogPost::find($post->id))->toBeNull();
});
