<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameModeTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KeywordRequest;
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
        return inertia('Admin/Keywords/KeywordForm', [
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Keyword $keyword)
    {
        return inertia('Admin/Keywords/KeywordForm', [
            'keyword' => $keyword,
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(KeywordRequest $request)
    {
        $validated = $request->validated();

        $validated['slug'] = Str::slug($validated['name']);

        $keyword = Keyword::create($validated);

        return redirect()->route('admin.keywords.index')->withMessage("{$keyword->name} created successfully.");
    }

    public function update(KeywordRequest $request, Keyword $keyword)
    {
        $validated = $request->validated();

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
