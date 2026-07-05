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
        PermissionEnum::DeletePosts,
    ] as $perm) {
        Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
    }

    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo([PermissionEnum::CreatePosts->value, PermissionEnum::EditPosts->value, PermissionEnum::DeletePosts->value]);
});

it('index excludes news categories', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    $articleCategory = BlogCategory::factory()->create();

    $this->actingAs($this->editor)
        ->get(route('admin.blog.categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('categories', 1)->where('categories.0.id', $articleCategory->id));

    expect($newsCategory->is_news)->toBeTrue();
});

it('store forces is_news to false regardless of payload', function () {
    $this->actingAs($this->editor)
        ->post(route('admin.blog.categories.store'), [
            'name' => 'New Category',
            'is_news' => true,
        ])
        ->assertRedirect(route('admin.blog.categories.index'));

    $category = BlogCategory::where('name', 'New Category')->firstOrFail();
    expect($category->is_news)->toBeFalse();
});

it('edit/update/delete 404 a news category reached via a Blog admin URL', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $this->actingAs($this->editor)->get(route('admin.blog.categories.edit', $newsCategory->slug))->assertNotFound();
    $this->actingAs($this->editor)->post(route('admin.blog.categories.update', $newsCategory->slug), ['name' => 'X'])->assertNotFound();
    $this->actingAs($this->editor)->post(route('admin.blog.categories.delete', $newsCategory->slug))->assertNotFound();
});

it('delete is blocked while the category still has posts', function () {
    $category = BlogCategory::factory()->create();
    BlogPost::factory()->create(['blog_category_id' => $category->id]);

    $this->actingAs($this->editor)
        ->post(route('admin.blog.categories.delete', $category->slug))
        ->assertRedirect(route('admin.blog.categories.index'));

    expect(BlogCategory::find($category->id))->not->toBeNull();
});

it('delete removes an empty category', function () {
    $category = BlogCategory::factory()->create();

    $this->actingAs($this->editor)
        ->post(route('admin.blog.categories.delete', $category->slug))
        ->assertRedirect(route('admin.blog.categories.index'));

    expect(BlogCategory::find($category->id))->toBeNull();
});
