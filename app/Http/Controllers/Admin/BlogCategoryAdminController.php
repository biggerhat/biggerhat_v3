<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogCategoryAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Blog/Categories/Index', [
            'categories' => BlogCategory::excludingNews()->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blog/Categories/BlogCategoryForm');
    }

    public function edit(Request $request, BlogCategory $blogCategory)
    {
        $this->guardNotNews($blogCategory);

        return inertia('Admin/Blog/Categories/BlogCategoryForm', [
            'category' => $blogCategory,
        ]);
    }

    public function store(CategoryRequest $request)
    {
        $validated = $request->validated();

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_news'] = false;

        $category = BlogCategory::create($validated);

        return redirect()->route('admin.blog.categories.index')->withMessage("{$category->name} created successfully.");
    }

    public function update(CategoryRequest $request, BlogCategory $blogCategory)
    {
        $this->guardNotNews($blogCategory);

        $validated = $request->validated();

        $blogCategory->update($validated);

        return redirect()->route('admin.blog.categories.index')->withMessage("{$blogCategory->name} has been updated.");
    }

    public function delete(Request $request, BlogCategory $blogCategory)
    {
        $this->guardNotNews($blogCategory);

        if ($blogCategory->posts()->exists()) {
            return redirect()->route('admin.blog.categories.index')->withMessage(
                "{$blogCategory->name} still has posts assigned to it — reassign or delete them first.",
                null,
                MessageTypeEnum::error,
            );
        }

        $name = $blogCategory->name;
        $blogCategory->delete();

        return redirect()->route('admin.blog.categories.index')->withMessage("{$name} has been deleted.");
    }

    /** A news category reached via direct URL must 404 here, not just be hidden from the index. */
    private function guardNotNews(BlogCategory $blogCategory): void
    {
        if ($blogCategory->is_news) {
            abort(404);
        }
    }
}
