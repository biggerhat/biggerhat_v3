<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoreMediaRequest;
use App\Models\LoreMedia;
use Illuminate\Http\Request;

class LoreMediaAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/LoreMedia/Index', [
            'lore_media' => LoreMedia::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/LoreMedia/LoreMediaForm', [
            'media_types' => LoreMediaTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, LoreMedia $loreMedia)
    {
        return inertia('Admin/LoreMedia/LoreMediaForm', [
            'lore_media' => $loreMedia,
            'media_types' => LoreMediaTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(LoreMediaRequest $request)
    {
        $validated = $request->validated();

        $loreMedia = LoreMedia::create($validated);

        return redirect()->route('admin.lore_media.index')->withMessage("{$loreMedia->name} created successfully.");
    }

    public function update(LoreMediaRequest $request, LoreMedia $loreMedia)
    {
        $validated = $request->validated();

        $loreMedia->update($validated);

        return redirect()->route('admin.lore_media.index')->withMessage("{$loreMedia->name} has been updated.");
    }

    public function delete(Request $request, LoreMedia $loreMedia)
    {
        $name = $loreMedia->name;
        $loreMedia->delete();

        return redirect()->route('admin.lore_media.index')->withMessage("{$name} has been deleted.");
    }
}
