<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FactionEnum;
use App\Enums\GameSystemEnum;
use App\Enums\PackageCategoryEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageRequest;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\TOS\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Storage;
use Str;

class PackageAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Packages/Index', [
            'packages' => Package::withCount(['characters', 'miniatures', 'tosUnits', 'keywords'])
                ->with('keywords:id,name')
                ->orderBy('name', 'ASC')
                ->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'slug' => $package->slug,
                    'name' => $package->name,
                    'game_system' => $package->game_system,
                    'category' => $package->category?->value,
                    'category_label' => $package->category?->label(),
                    'is_auto_generated' => $package->is_auto_generated,
                    'factions' => $package->factions,
                    'characters_count' => $package->characters_count,
                    'miniatures_count' => $package->miniatures_count,
                    'tos_units_count' => $package->tos_units_count,
                    'keywords_count' => $package->keywords_count,
                    'keywords' => $package->keywords->pluck('name'),
                ]),
            'categories' => fn () => PackageCategoryEnum::toSelectOptions(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Packages/PackageForm', $this->getFormData());
    }

    public function edit(Request $request, Package $package)
    {
        return inertia('Admin/Packages/PackageForm', array_merge(
            ['package' => $package->loadMissing(['characters', 'miniatures', 'keywords', 'tosUnits'])],
            $this->getFormData(),
        ));
    }

    public function store(PackageRequest $request)
    {
        $package = $this->validateAndSave($request);

        return redirect()->route('admin.packages.index')->withMessage("{$package->name} created successfully.");
    }

    public function update(PackageRequest $request, Package $package)
    {
        $package = $this->validateAndSave($request, $package);

        return redirect()->route('admin.packages.index')->withMessage("{$package->name} has been updated.");
    }

    public function delete(Request $request, Package $package)
    {
        $name = $package->name;
        $package->delete();

        return redirect()->route('admin.packages.index')->withMessage("{$name} has been deleted.");
    }

    private function getFormData(): array
    {
        return [
            'factions' => fn () => FactionEnum::toSelectOptions(),
            'game_systems' => fn () => GameSystemEnum::toSelectOptions(),
            'sculpt_versions' => fn () => SculptVersionEnum::toSelectOptions(),
            'categories' => fn () => PackageCategoryEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'id'),
            'miniatures' => fn () => Miniature::toSelectOptions('display_name', 'id'),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'id'),
            'tos_units' => fn () => Unit::orderBy('name')
                ->get(['slug', 'name', 'title'])
                ->map(fn (Unit $u) => ['name' => $u->title ? "{$u->name} — {$u->title}" : $u->name, 'value' => $u->slug])
                ->all(),
        ];
    }

    private function validateAndSave(PackageRequest $request, ?Package $package = null): Package
    {
        $characters = collect([]);
        $miniatures = collect([]);
        $keywords = collect([]);
        $tosUnits = collect([]);

        $validated = $request->validated();

        $characterQuantities = $validated['character_quantities'] ?? [];
        unset($validated['character_quantities']);

        $characterSpecialOrders = $validated['character_special_orders'] ?? [];
        unset($validated['character_special_orders']);

        $validated['slug'] = Str::slug($validated['name']);

        // Handle Images
        if (isset($validated['front_image']) && $validated['front_image']) {
            $extension = $validated['front_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_front.%s', $validated['slug'], $uuid, $extension);
            $filePath = "packages/{$validated['slug']}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['front_image']));
            $validated['front_image'] = $filePath;
        } else {
            unset($validated['front_image']);
        }

        if (isset($validated['back_image']) && $validated['back_image']) {
            $extension = $validated['back_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_back.%s', $validated['slug'], $uuid, $extension);
            $filePath = "packages/{$validated['slug']}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['back_image']));
            $validated['back_image'] = $filePath;
        } else {
            unset($validated['back_image']);
        }

        if (isset($validated['combination_image']) && $validated['combination_image']) {
            $extension = $validated['combination_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_combo.%s', $validated['slug'], $uuid, $extension);
            $filePath = "packages/{$validated['slug']}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['combination_image']));
            $validated['combination_image'] = $filePath;
        } else {
            unset($validated['combination_image']);
        }

        if (isset($validated['characters'])) {
            $characters = Character::whereIn('display_name', $validated['characters'])->get();
            unset($validated['characters']);
        }

        if (isset($validated['miniatures'])) {
            $miniatures = Miniature::whereIn('display_name', $validated['miniatures'])->get();
            unset($validated['miniatures']);
        }

        if (isset($validated['keywords'])) {
            $keywords = Keyword::whereIn('name', $validated['keywords'])->get();
            unset($validated['keywords']);
        }

        if (isset($validated['tos_units'])) {
            $tosUnits = Unit::whereIn('slug', $validated['tos_units'])->get();
            unset($validated['tos_units']);
        }

        return DB::transaction(function () use ($validated, $package, $characters, $miniatures, $keywords, $tosUnits, $characterQuantities, $characterSpecialOrders) {
            if (! $package) {
                $package = Package::create($validated);
            } else {
                $package->update($validated);
            }

            $characterSyncData = $characters->mapWithKeys(fn (Character $c) => [
                $c->id => [
                    'quantity' => $characterQuantities[$c->display_name] ?? 1,
                    'special_order' => (bool) ($characterSpecialOrders[$c->display_name] ?? false),
                ],
            ]);

            $package->characters()->sync($characterSyncData);
            $package->miniatures()->sync($miniatures->pluck('id'));
            $package->keywords()->sync($keywords->pluck('id'));
            $package->tosUnits()->sync($tosUnits->pluck('id'));

            return $package;
        });
    }
}
