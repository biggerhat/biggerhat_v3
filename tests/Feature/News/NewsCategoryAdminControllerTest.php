<?php

use App\Enums\PermissionEnum;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => PermissionEnum::ManageNews->value, 'guard_name' => 'web']);

    $this->newsEditor = User::factory()->create();
    $this->newsEditor->givePermissionTo(PermissionEnum::ManageNews->value);

    $this->stranger = User::factory()->create();
});

it('index only returns news categories', function () {
    $newsCategory = BlogCategory::factory()->news()->create(['name' => 'A Distinct News Category']);
    BlogCategory::factory()->create();

    $this->actingAs($this->newsEditor)
        ->get(route('admin.news.categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('categories', fn ($categories) => collect($categories)->pluck('id')->contains($newsCategory->id)));
});

it('is forbidden without manage_news', function () {
    $this->actingAs($this->stranger)
        ->get(route('admin.news.categories.index'))
        ->assertForbidden();
});

it('store always creates a category with is_news = true regardless of payload', function () {
    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.categories.store'), [
            'name' => 'How-To',
            'is_news' => false,
        ])
        ->assertRedirect(route('admin.news.categories.index'));

    $category = BlogCategory::where('name', 'How-To')->firstOrFail();
    expect($category->is_news)->toBeTrue();
});

it('edit/update/delete 404 a non-news category reached via a News admin URL', function () {
    $articleCategory = BlogCategory::factory()->create();

    $this->actingAs($this->newsEditor)->get(route('admin.news.categories.edit', $articleCategory->slug))->assertNotFound();
    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.categories.update', $articleCategory->slug), ['name' => 'X'])
        ->assertNotFound();
    $this->actingAs($this->newsEditor)->post(route('admin.news.categories.delete', $articleCategory->slug))->assertNotFound();
});

it('delete is blocked while the news category still has posts', function () {
    $newsCategory = BlogCategory::factory()->news()->create();
    BlogPost::factory()->create(['blog_category_id' => $newsCategory->id]);

    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.categories.delete', $newsCategory->slug))
        ->assertRedirect(route('admin.news.categories.index'));

    expect(BlogCategory::find($newsCategory->id))->not->toBeNull();
});

it('delete removes an empty news category', function () {
    $newsCategory = BlogCategory::factory()->news()->create();

    $this->actingAs($this->newsEditor)
        ->post(route('admin.news.categories.delete', $newsCategory->slug))
        ->assertRedirect(route('admin.news.categories.index'));

    expect(BlogCategory::find($newsCategory->id))->toBeNull();
});
