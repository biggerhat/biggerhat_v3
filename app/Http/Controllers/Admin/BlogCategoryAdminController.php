<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogCategoryAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Blog/Categories/Index', [
            'categories' => BlogCategory::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blog/Categories/BlogCategoryForm');
    }

    public function edit(Request $request, BlogCategory $blogCategory)
    {
        return inertia('Admin/Blog/Categories/BlogCategoryForm', [
            'category' => $blogCategory,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = BlogCategory::create($validated);

        return redirect()->route('admin.blog.categories.index')->withMessage("{$category->name} created successfully.");
    }

    public function update(Request $request, BlogCategory $blogCategory)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $blogCategory->update($validated);

        return redirect()->route('admin.blog.categories.index')->withMessage("{$blogCategory->name} has been updated.");
    }

    public function delete(Request $request, BlogCategory $blogCategory)
    {
        $name = $blogCategory->name;
        $blogCategory->delete();

        return redirect()->route('admin.blog.categories.index')->withMessage("{$name} has been deleted.");
    }
}
