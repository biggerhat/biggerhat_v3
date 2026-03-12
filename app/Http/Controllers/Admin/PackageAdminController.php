<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Http\Request;
use Storage;
use Str;

class PackageAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Packages/Index', [
            'packages' => Package::withCount(['characters', 'miniatures'])
                ->orderBy('name', 'ASC')
                ->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Packages/PackageForm', $this->getFormData());
    }

    public function edit(Request $request, Package $package)
    {
        return inertia('Admin/Packages/PackageForm', array_merge(
            ['package' => $package->loadMissing(['characters', 'miniatures', 'keywords'])],
            $this->getFormData(),
        ));
    }

    public function store(Request $request)
    {
        $package = $this->validateAndSave($request);

        return redirect()->route('admin.packages.index')->withMessage("{$package->name} created successfully.");
    }

    public function update(Request $request, Package $package)
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
            'sculpt_versions' => fn () => SculptVersionEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'id'),
            'miniatures' => fn () => Miniature::toSelectOptions('display_name', 'id'),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'id'),
        ];
    }

    private function validateAndSave(Request $request, ?Package $package = null): Package
    {
        $characters = collect([]);
        $miniatures = collect([]);
        $keywords = collect([]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'factions' => ['nullable', 'array'],
            'factions.*' => ['string'],
            'sku' => ['nullable', 'string', 'max:255'],
            'upc' => ['nullable', 'string', 'max:255'],
            'msrp' => ['nullable', 'integer'],
            'distributor_description' => ['nullable', 'string'],
            'sculpt_version' => ['nullable', 'string'],
            'is_preassembled' => ['nullable', 'boolean'],
            'released_at' => ['nullable', 'date'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'characters' => ['nullable', 'array'],
            'miniatures' => ['nullable', 'array'],
            'keywords' => ['nullable', 'array'],
        ]);

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

        if (! $package) {
            $package = Package::create($validated);
        } else {
            $package->update($validated);
        }

        $package->characters()->sync($characters->pluck('id'));
        $package->miniatures()->sync($miniatures->pluck('id'));
        $package->keywords()->sync($keywords->pluck('id'));

        if ($package->front_image && $package->back_image && ! $package->combination_image) {
            $this->generateComboImage($package);
        } elseif ($package->front_image && ! $package->back_image) {
            $package->update([
                'combination_image' => $package->front_image,
            ]);
        }

        return $package;
    }

    private function generateComboImage(Package $package): void
    {
        [$widthFront, $heightFront] = getimagesize(Storage::disk('public')->path($package->front_image));
        [$widthBack, $heightBack] = getimagesize(Storage::disk('public')->path($package->back_image));
        $background = imagecreatetruecolor($widthFront + $widthBack, $heightFront);

        header('Content-Type: image/jpeg');
        $outputImage = $background;

        $frontUrl = imagecreatefromjpeg(Storage::disk('public')->path($package->front_image));
        $backUrl = imagecreatefromjpeg(Storage::disk('public')->path($package->back_image));

        imagecopymerge($outputImage, $frontUrl, 0, 0, 0, 0, $widthFront, $heightFront, 100);
        imagecopymerge($outputImage, $backUrl, $widthFront, 0, 0, 0, $widthBack, $heightBack, 100);

        $extension = 'jpg';
        $uuid = Str::uuid();
        $fileName = sprintf('%s_%s_combo.%s', $package->slug, $uuid, $extension);
        $filePath = "packages/{$package->slug}/{$fileName}";

        $path = Storage::disk('public')->path('/');
        imagejpeg($outputImage, $path.$filePath);
        $package->update(['combination_image' => $filePath]);
        imagedestroy($outputImage);
    }
}
