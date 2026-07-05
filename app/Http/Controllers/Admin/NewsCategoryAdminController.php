<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * News category admin — reuses the Blog category CRUD infrastructure
 * (BlogCategory model, BlogCategoryForm.vue). store()/update() force
 * is_news = true server-side so a category created here can never
 * accidentally land in the public Blog listing, and vice versa.
 */
class NewsCategoryAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Blog/Categories/Index', [
            'categories' => BlogCategory::news()->orderBy('name', 'ASC')->get(),
            'postType' => 'news',
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blog/Categories/BlogCategoryForm', ['postType' => 'news']);
    }

    public function edit(Request $request, BlogCategory $blogCategory)
    {
        $this->guardIsNews($blogCategory);

        return inertia('Admin/Blog/Categories/BlogCategoryForm', [
            'category' => $blogCategory,
            'postType' => 'news',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_news'] = true;

        $category = BlogCategory::create($validated);

        return redirect()->route('admin.news.categories.index')->withMessage("{$category->name} created successfully.");
    }

    public function update(Request $request, BlogCategory $blogCategory)
    {
        $this->guardIsNews($blogCategory);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $validated['is_news'] = true;

        $blogCategory->update($validated);

        return redirect()->route('admin.news.categories.index')->withMessage("{$blogCategory->name} has been updated.");
    }

    public function delete(Request $request, BlogCategory $blogCategory)
    {
        $this->guardIsNews($blogCategory);

        if ($blogCategory->posts()->exists()) {
            return redirect()->route('admin.news.categories.index')->withMessage(
                "{$blogCategory->name} still has posts assigned to it — reassign or delete them first.",
                null,
                MessageTypeEnum::error,
            );
        }

        $name = $blogCategory->name;
        $blogCategory->delete();

        return redirect()->route('admin.news.categories.index')->withMessage("{$name} has been deleted.");
    }

    /** A Blog-only category reached via a News admin URL must 404 here. */
    private function guardIsNews(BlogCategory $blogCategory): void
    {
        if (! $blogCategory->is_news) {
            abort(404);
        }
    }
}
