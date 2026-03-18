<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\MiniatureResource;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Storage;
use Str;

class MiniatureAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Miniatures/Index', [
            'miniatures' => MiniatureResource::collection(Miniature::with('character')->orderBy('display_name', 'ASC')->get())
                ->toArray($request),
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

        return redirect()->route('admin.miniatures.index')->withMessage("{$miniature->display_name} created successfully.");
    }

    public function update(Request $request, Miniature $miniature)
    {
        $miniature = $this->validateAndSave($request, $miniature);

        return redirect()->route('admin.miniatures.index')->withMessage("{$miniature->display_name} has been updated.");
    }

    public function delete(Request $request, Miniature $miniature)
    {
        $name = $miniature->display_name;
        $miniature->delete();

        return redirect()->route('admin.miniatures.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Miniature $miniature = null): Miniature
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'character_id' => ['required'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'version' => ['required', 'string', Rule::enum(SculptVersionEnum::class)],
            'use_existing_back' => ['nullable', 'boolean'],
        ]);

        $character = Character::findOrFail($validated['character_id']);
        $displayName = $validated['name'] ?? $character->name;

        if ($validated['name'] && $validated['title']) {
            $displayName .= ', '.$validated['title'];
        } elseif (! $validated['name'] && $character->title) {
            $displayName .= ', '.$character->title;
        }

        // Append promotional title for special editions without custom name/title
        if (! $validated['name'] && ! $validated['title']) {
            $version = SculptVersionEnum::from($validated['version']);
            $promoTitle = $version->promotionalTitle();
            if ($promoTitle) {
                $displayName .= ' ('.$promoTitle.')';
            }
        }

        $validated['display_name'] = $displayName;
        $validated['slug'] = Str::slug($displayName);

        // Handle Images
        if ($validated['front_image']) {
            $extension = $validated['front_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_front.%s', $character->id, $uuid, $extension);
            $filePath = "characters/{$character->id}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['front_image']));
            $validated['front_image'] = $filePath;
        } else {
            unset($validated['front_image']);
        }

        $useExistingBack = $validated['use_existing_back'] ?? false;
        unset($validated['use_existing_back']);

        if ($useExistingBack) {
            $existingMiniature = Miniature::where('character_id', $character->id)
                ->whereNotNull('back_image')
                ->whereIn('version', collect(SculptVersionEnum::standardEditions())->map->value)
                ->when($miniature, fn ($q) => $q->where('id', '!=', $miniature->id))
                ->first();

            if ($existingMiniature) {
                $validated['back_image'] = $existingMiniature->back_image;
            } else {
                unset($validated['back_image']);
            }
        } elseif ($validated['back_image']) {
            $extension = $validated['back_image']->extension();
            $uuid = Str::uuid();
            $fileName = sprintf('%s_%s_back.%s', $character->id, $uuid, $extension);
            $filePath = "characters/{$character->id}/{$fileName}";
            Storage::disk('public')->put($filePath, file_get_contents($validated['back_image']));
            $validated['back_image'] = $filePath;
        } else {
            unset($validated['back_image']);
        }

        if (! $miniature) {
            $miniature = Miniature::create($validated);
        } else {
            $miniature->update($validated);
        }

        if ($miniature->front_image && $miniature->back_image) {
            $this->generateComboImage($miniature);
        } elseif ($miniature->front_image && ! $miniature->back_image) {
            $miniature->update(['combination_image' => $miniature->front_image]);
        }

        return $miniature;
    }

    private function generateComboImage(Miniature $miniature)
    {
        $targetWidth = 550;
        $targetHeight = 950;

        $frontSrc = imagecreatefromjpeg(Storage::disk('public')->path($miniature->front_image));
        $backSrc = imagecreatefromjpeg(Storage::disk('public')->path($miniature->back_image));

        $front = $this->resizeToTarget($frontSrc, $targetWidth, $targetHeight);
        $back = $this->resizeToTarget($backSrc, $targetWidth, $targetHeight);

        imagedestroy($frontSrc);
        imagedestroy($backSrc);

        $outputImage = imagecreatetruecolor($targetWidth * 2, $targetHeight);
        imagecopy($outputImage, $front, 0, 0, 0, 0, $targetWidth, $targetHeight);
        imagecopy($outputImage, $back, $targetWidth, 0, 0, 0, $targetWidth, $targetHeight);

        imagedestroy($front);
        imagedestroy($back);

        $uuid = Str::uuid();
        $fileName = sprintf('%s_%s_combo.jpg', $miniature->character_id, $uuid);
        $filePath = "characters/{$miniature->character_id}/{$fileName}";

        imagejpeg($outputImage, Storage::disk('public')->path($filePath));
        $miniature->update(['combination_image' => $filePath]);
        imagedestroy($outputImage);
    }

    private function resizeToTarget(\GdImage $source, int $targetWidth, int $targetHeight): \GdImage
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        if ($srcWidth === $targetWidth && $srcHeight === $targetHeight) {
            return $source;
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);

        return $resized;
    }
}
