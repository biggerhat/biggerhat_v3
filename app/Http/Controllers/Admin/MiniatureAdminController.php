<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Str;

class MiniatureAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Miniatures/Index', [
            'miniatures' => Miniature::with('character')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Miniatures/MiniatureForm', [
            'characters' => Character::toSelectOptions('display_name', 'id'),
            'version_types' => SculptVersionEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Miniature $miniature)
    {
        return inertia('Admin/Miniatures/MiniatureForm', [
            'miniature' => $miniature->loadMissing(['character']),
            'characters' => Character::toSelectOptions('display_name', 'id'),
            'version_types' => SculptVersionEnum::toSelectOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $miniature = $this->validateAndSave($request);

        return redirect()->route('admin.miniatures.index')->withMessage("{$miniature->name} created successfully.");
    }

    public function update(Request $request, Miniature $miniature)
    {
        $miniature = $this->validateAndSave($request, $miniature);

        return redirect()->route('admin.miniatures.index')->withMessage("{$miniature->name} has been updated.");
    }

    public function delete(Request $request, Miniature $miniature)
    {
        $name = $miniature->name;
        $miniature->delete();

        return redirect()->route('admin.miniatures.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Miniature $miniature = null): Miniature
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'character_id' => ['required'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'version' => ['required', 'string', Rule::enum(SculptVersionEnum::class)],
        ]);

        $character = Character::findOrFail($validated['character_id']);
        $displayName = $validated['name'] ?? $character->name;

        if ($validated['name'] && $validated['title']) {
            $displayName .= ', '.$validated['title'];
        } elseif (!$validated['name'] && $character->title) {
            $displayName .= ', '. $character->title;
        }

        $validated['display_name'] = $displayName;
        $validated['slug'] = Str::slug($displayName);

        if (! $miniature) {
            $miniature = Miniature::create($validated);
        } else {
            $miniature->update($validated);
        }

        return $miniature;
    }
}
