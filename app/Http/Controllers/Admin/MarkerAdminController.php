<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BaseSizeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarkerRequest;
use App\Models\Character;
use App\Models\Marker;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarkerAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Markers/Index', [
            'markers' => Marker::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Markers/MarkerForm', $this->getFormData());
    }

    public function edit(Request $request, Marker $marker)
    {
        return inertia('Admin/Markers/MarkerForm', array_merge(
            ['marker' => $marker->loadMissing(['characters', 'upgrades'])],
            $this->getFormData(),
        ));
    }

    public function store(MarkerRequest $request)
    {
        $validated = $request->validated();

        $characterIds = Character::whereIn('slug', $validated['characters'] ?? [])->pluck('id');
        $upgradeIds = Upgrade::whereIn('slug', $validated['upgrades'] ?? [])->pluck('id');
        unset($validated['characters'], $validated['upgrades']);

        $validated['slug'] = Str::slug($validated['name']);

        $marker = Marker::create($validated);
        $marker->characters()->sync($characterIds);
        $marker->upgrades()->sync($upgradeIds);

        return redirect()->route('admin.markers.index')->withMessage("{$marker->name} created successfully.");
    }

    public function update(MarkerRequest $request, Marker $marker)
    {
        $validated = $request->validated();

        $characterIds = Character::whereIn('slug', $validated['characters'] ?? [])->pluck('id');
        $upgradeIds = Upgrade::whereIn('slug', $validated['upgrades'] ?? [])->pluck('id');
        unset($validated['characters'], $validated['upgrades']);

        $marker->update($validated);
        $marker->characters()->sync($characterIds);
        $marker->upgrades()->sync($upgradeIds);

        return redirect()->route('admin.markers.index')->withMessage("{$marker->name} has been updated.");
    }

    public function delete(Request $request, Marker $marker)
    {
        $name = $marker->name;
        $marker->delete();

        return redirect()->route('admin.markers.index')->withMessage("{$name} has been deleted.");
    }

    private function getFormData(): array
    {
        return [
            'base_sizes' => fn () => BaseSizeEnum::toSelectOptions(),
            'all_characters' => fn () => Character::orderBy('display_name')->toSelectOptions('display_name', 'slug'),
            'all_upgrades' => fn () => Upgrade::orderBy('name')->toSelectOptions('name', 'slug'),
        ];
    }
}
