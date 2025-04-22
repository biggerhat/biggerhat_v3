<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KeywordAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Keywords/Index', [
            'keywords' => Keyword::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Keywords/KeywordForm');
    }

    public function edit(Request $request, Keyword $keyword)
    {
        return inertia('Admin/Keywords/KeywordForm', [
            'keyword' => $keyword,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $keyword = Keyword::create($validated);

        return redirect()->route('admin.keywords.index')->withMessage("{$keyword->name} created successfully.");
    }

    public function update(Request $request, Keyword $keyword)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $keyword->update($validated);

        return redirect()->route('admin.keywords.index')->withMessage("{$keyword->name} has been updated.");
    }

    public function delete(Request $request, Keyword $keyword)
    {
        $name = $keyword->name;
        $keyword->delete();

        return redirect()->route('admin.keywords.index')->withMessage("{$name} has been deleted.");
    }
}
