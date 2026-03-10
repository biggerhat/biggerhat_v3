<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoreAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Lore/Index', [
            'lores' => Lore::with('media', 'characters')->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Lore/LoreForm', [
            'lore_media' => collect(LoreMedia::toSelectOptions('name', 'id'))->map(fn ($opt) => ['name' => $opt['name'], 'value' => (string) $opt['value']]),
            'media_types' => LoreMediaTypeEnum::toSelectOptions(),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function edit(Request $request, Lore $lore)
    {
        return inertia('Admin/Lore/LoreForm', [
            'lore' => $lore->loadMissing(['media', 'characters']),
            'lore_media' => collect(LoreMedia::toSelectOptions('name', 'id'))->map(fn ($opt) => ['name' => $opt['name'], 'value' => (string) $opt['value']]),
            'media_types' => LoreMediaTypeEnum::toSelectOptions(),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function store(Request $request)
    {
        $lore = $this->validateAndSave($request);

        return redirect()->route('admin.lores.index')->withMessage("{$lore->name} created successfully.");
    }

    public function update(Request $request, Lore $lore)
    {
        $lore = $this->validateAndSave($request, $lore);

        return redirect()->route('admin.lores.index')->withMessage("{$lore->name} has been updated.");
    }

    public function delete(Request $request, Lore $lore)
    {
        $name = $lore->name;
        $lore->delete();

        return redirect()->route('admin.lores.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Lore $lore = null): Lore
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lore_media_id' => ['nullable', 'exists:lore_media,id'],
            'characters' => ['nullable', 'array'],
            // Inline new media creation
            'new_media_name' => ['nullable', 'string', 'max:255'],
            'new_media_type' => ['nullable', 'string', Rule::enum(LoreMediaTypeEnum::class)],
            'new_media_link' => ['nullable', 'string', 'max:500'],
        ]);

        // If creating new media inline
        if (! empty($validated['new_media_name']) && ! empty($validated['new_media_type'])) {
            $newMedia = LoreMedia::create([
                'name' => $validated['new_media_name'],
                'type' => $validated['new_media_type'],
                'link' => $validated['new_media_link'] ?? null,
            ]);
            $validated['lore_media_id'] = $newMedia->id;
        }

        $characters = Character::whereIn('display_name', $validated['characters'] ?? [])->get();
        unset($validated['characters'], $validated['new_media_name'], $validated['new_media_type'], $validated['new_media_link']);

        if (! $lore) {
            $lore = Lore::create($validated);
        } else {
            $lore->update($validated);
        }

        $lore->characters()->sync($characters->pluck('id'));

        return $lore;
    }
}
