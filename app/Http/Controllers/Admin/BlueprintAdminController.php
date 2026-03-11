<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlueprintAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Blueprints/Index', [
            'blueprints' => Blueprint::with(['characters', 'miniatures', 'packages'])
                ->orderBy('name', 'ASC')
                ->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Blueprints/BlueprintForm', [
            'sculpt_versions' => SculptVersionEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'display_name'),
            'miniatures' => fn () => Miniature::toSelectOptions('display_name', 'display_name'),
            'packages' => fn () => Package::toSelectOptions('name', 'name'),
        ]);
    }

    public function edit(Request $request, Blueprint $blueprint)
    {
        return inertia('Admin/Blueprints/BlueprintForm', [
            'blueprint' => $blueprint->loadMissing(['characters', 'miniatures', 'packages']),
            'sculpt_versions' => SculptVersionEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'display_name'),
            'miniatures' => fn () => Miniature::toSelectOptions('display_name', 'display_name'),
            'packages' => fn () => Package::toSelectOptions('name', 'name'),
        ]);
    }

    public function store(Request $request)
    {
        $blueprint = $this->validateAndSave($request);

        return redirect()->route('admin.blueprints.index')->withMessage("{$blueprint->name} created successfully.");
    }

    public function update(Request $request, Blueprint $blueprint)
    {
        $blueprint = $this->validateAndSave($request, $blueprint);

        return redirect()->route('admin.blueprints.index')->withMessage("{$blueprint->name} has been updated.");
    }

    public function delete(Request $request, Blueprint $blueprint)
    {
        $name = $blueprint->name;
        $blueprint->delete();

        return redirect()->route('admin.blueprints.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Blueprint $blueprint = null): Blueprint
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'sculpt_version' => ['required', 'string', Rule::enum(SculptVersionEnum::class)],
            'published_at' => ['nullable', 'date'],
            'characters' => ['nullable', 'array'],
            'miniatures' => ['nullable', 'array'],
            'packages' => ['nullable', 'array'],
        ]);

        $data = [
            'name' => $validated['name'],
            'source_url' => $validated['source_url'] ?? null,
            'sculpt_version' => $validated['sculpt_version'],
            'published_at' => $validated['published_at'] ?? null,
        ];

        if (! $blueprint) {
            $blueprint = Blueprint::create($data);
        } else {
            $blueprint->update($data);
        }

        $characterIds = Character::whereIn('display_name', $validated['characters'] ?? [])->pluck('id')->toArray();
        $blueprint->characters()->sync($characterIds);

        $miniatureIds = Miniature::whereIn('display_name', $validated['miniatures'] ?? [])->pluck('id')->toArray();
        $blueprint->miniatures()->sync($miniatureIds);

        $packageIds = Package::whereIn('name', $validated['packages'] ?? [])->pluck('id')->toArray();
        $blueprint->packages()->sync($packageIds);

        return $blueprint;
    }
}
