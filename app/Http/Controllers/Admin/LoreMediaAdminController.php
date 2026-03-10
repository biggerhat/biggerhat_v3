<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\LoreMedia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(LoreMediaTypeEnum::class)],
            'link' => ['nullable', 'string', 'max:500'],
        ]);

        $loreMedia = LoreMedia::create($validated);

        return redirect()->route('admin.lore_media.index')->withMessage("{$loreMedia->name} created successfully.");
    }

    public function update(Request $request, LoreMedia $loreMedia)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(LoreMediaTypeEnum::class)],
            'link' => ['nullable', 'string', 'max:500'],
        ]);

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
