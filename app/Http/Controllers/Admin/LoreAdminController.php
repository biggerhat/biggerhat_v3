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
            'lore_media' => fn () => LoreMedia::orderBy('name')->get()->map(fn (LoreMedia $m) => [
                'name' => $m->name,
                'value' => $m->name,
            ]),
            'media_types' => fn () => LoreMediaTypeEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function edit(Request $request, Lore $lore)
    {
        return inertia('Admin/Lore/LoreForm', [
            'lore' => $lore->loadMissing(['media', 'characters']),
            'lore_media' => fn () => LoreMedia::orderBy('name')->get()->map(fn (LoreMedia $m) => [
                'name' => $m->name,
                'value' => $m->name,
            ]),
            'media_types' => fn () => LoreMediaTypeEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
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
            'lore_media' => ['nullable', 'array'],
            'characters' => ['nullable', 'array'],
            // Inline new media creation (array of objects)
            'new_media' => ['nullable', 'array'],
            'new_media.*.name' => ['required_with:new_media', 'string', 'max:255'],
            'new_media.*.type' => ['required_with:new_media', 'string', Rule::enum(LoreMediaTypeEnum::class)],
            'new_media.*.link' => ['nullable', 'string', 'max:500'],
        ]);

        // Resolve existing media by name
        $mediaIds = LoreMedia::whereIn('name', $validated['lore_media'] ?? [])->pluck('id')->toArray();

        // Create new inline media and collect their IDs
        foreach ($validated['new_media'] ?? [] as $newMediaData) {
            if (! empty($newMediaData['name']) && ! empty($newMediaData['type'])) {
                $newMedia = LoreMedia::create([
                    'name' => $newMediaData['name'],
                    'type' => $newMediaData['type'],
                    'link' => $newMediaData['link'] ?? null,
                ]);
                $mediaIds[] = $newMedia->id;
            }
        }

        $characters = Character::whereIn('display_name', $validated['characters'] ?? [])->get();

        if (! $lore) {
            $lore = Lore::create(['name' => $validated['name']]);
        } else {
            $lore->update(['name' => $validated['name']]);
        }

        $lore->media()->sync($mediaIds);
        $lore->characters()->sync($characters->pluck('id'));

        return $lore;
    }
}
