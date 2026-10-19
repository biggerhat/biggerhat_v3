<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoreMediaTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoreRequest;
use App\Models\Character;
use App\Models\Lore;
use App\Models\LoreMedia;
use App\Models\TOS\Unit as TosUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Str;

class LoreAdminController extends Controller
{
    public function index(Request $request)
    {
        // Sort by name, or by creation date (Newest/Oldest) so admins can find
        // recently-entered lore. `id` is the stable creation-order tiebreak.
        $sort = $request->get('sort') === 'created_at' ? 'created_at' : 'name';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';

        return inertia('Admin/Lore/Index', [
            'lores' => Lore::with('media', 'characters', 'tosUnits')
                ->orderBy($sort, $direction)
                ->orderBy('id', $direction)
                ->get(),
            'sort' => $sort,
            'direction' => $direction,
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
            'tos_units' => fn () => $this->tosUnitOptions(),
        ]);
    }

    public function edit(Request $request, Lore $lore)
    {
        return inertia('Admin/Lore/LoreForm', [
            'lore' => $lore->loadMissing(['media', 'characters', 'tosUnits']),
            'lore_media' => fn () => LoreMedia::orderBy('name')->get()->map(fn (LoreMedia $m) => [
                'name' => $m->name,
                'value' => $m->name,
            ]),
            'media_types' => fn () => LoreMediaTypeEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
            'tos_units' => fn () => $this->tosUnitOptions(),
        ]);
    }

    /**
     * TOS Unit options for the link picker — keyed by slug (Unit names collide
     * across title variants, so slug is the stable handle).
     *
     * @return array<int, array{name: string, value: string}>
     */
    private function tosUnitOptions(): array
    {
        return TosUnit::orderBy('name')
            ->get(['slug', 'name', 'title'])
            ->map(fn (TosUnit $u) => [
                'name' => $u->title ? "{$u->name} — {$u->title}" : $u->name,
                'value' => $u->slug,
            ])
            ->all();
    }

    public function store(LoreRequest $request)
    {
        $lore = $this->validateAndSave($request);

        return redirect()->route('admin.lores.index')->withMessage("{$lore->name} created successfully.");
    }

    public function update(LoreRequest $request, Lore $lore)
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

    private function validateAndSave(LoreRequest $request, ?Lore $lore = null): Lore
    {
        $validated = $request->validated();

        // Handle file upload
        $fileData = [];
        if (isset($validated['file']) && $validated['file']) {
            $extension = $validated['file']->extension() ?: $validated['file']->getClientOriginalExtension();
            $slug = Str::slug($validated['name']);
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s.%s', $slug, $uuid, $extension);
            $fileData['file'] = $validated['file']->storeAs('lore', $fileName, 'public');
        } elseif (! empty($validated['remove_file'])) {
            if ($lore?->file) {
                Storage::disk('public')->delete($lore->file);
            }
            $fileData['file'] = null;
        }

        return DB::transaction(function () use ($validated, $fileData, $lore) {
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
            $tosUnitIds = TosUnit::whereIn('slug', $validated['tos_units'] ?? [])->pluck('id');

            if (! $lore) {
                $lore = Lore::create(['name' => $validated['name'], ...$fileData]);
            } else {
                $lore->update(['name' => $validated['name'], ...$fileData]);
            }

            $lore->media()->sync($mediaIds);
            $lore->characters()->sync($characters->pluck('id'));
            $lore->tosUnits()->sync($tosUnitIds);

            return $lore;
        });
    }
}
