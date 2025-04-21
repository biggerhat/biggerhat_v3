<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterStationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Storage;
use Str;

class UpgradeAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Upgrades/Index', [
            'upgrades' => Upgrade::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Upgrades/UpgradeForm', [
            'characters' => Character::where('station', CharacterStationEnum::Master->value)->toSelectOptions('display_name', 'id'),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Upgrade $upgrade)
    {
        return inertia('Admin/Upgrades/UpgradeForm', [
            'upgrade' => $upgrade->loadMissing(['master']),
            'characters' => Character::where('station', CharacterStationEnum::Master->value)->toSelectOptions('display_name', 'id'),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $upgrade = $this->validateAndSave($request);

        return redirect()->route('admin.upgrades.index')->withMessage("{$upgrade->name} created successfully.");
    }

    public function update(Request $request, Upgrade $upgrade)
    {
        $upgrade = $this->validateAndSave($request, $upgrade);

        return redirect()->route('admin.upgrades.index')->withMessage("{$upgrade->name} has been updated.");
    }

    public function delete(Request $request, Upgrade $upgrade)
    {
        $name = $upgrade->name;
        $upgrade->delete();

        return redirect()->route('admin.upgrades.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Upgrade $upgrade = null): Upgrade
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(UpgradeTypeEnum::class)],
            'master_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'power_bar_count' => ['nullable', 'integer'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Handle Images
        if ($validated['front_image']) {
            $extension = $validated['front_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_front.%s', $validated['slug'], $uuid, $extension);
            $filePath = "upgrades/{$validated['slug']}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['front_image']));
            $validated['front_image'] = $filePath;
        } else {
            unset($validated['front_image']);
        }

        if ($validated['back_image']) {
            $extension = $validated['back_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_back.%s', $validated['slug'], $uuid, $extension);
            $filePath = "upgrades/{$validated['slug']}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['back_image']));
            $validated['back_image'] = $filePath;
        } else {
            unset($validated['back_image']);
        }

        if (! $upgrade) {
            $upgrade = Upgrade::create($validated);
        } else {
            $upgrade->update($validated);
        }

        return $upgrade;
    }
}
