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
        ]);

        $character = Character::findOrFail($validated['character_id']);
        $displayName = $validated['name'] ?? $character->name;

        if ($validated['name'] && $validated['title']) {
            $displayName .= ', '.$validated['title'];
        } elseif (! $validated['name'] && $character->title) {
            $displayName .= ', '.$character->title;
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

        if ($validated['back_image']) {
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

        $this->generateComboImage($miniature);

        return $miniature;
    }

    private function generateComboImage(Miniature $miniature)
    {
        [$widthFront, $heightFront] = getimagesize(Storage::disk('public')->path($miniature->front_image));
        [$widthBack, $heightBack] = getimagesize(Storage::disk('public')->path($miniature->back_image));
        $background = imagecreatetruecolor($widthFront + $widthBack, $heightFront);

        header('Content-Type: image/jpeg');
        $outputImage = $background;

        $frontUrl = imagecreatefromjpeg(Storage::disk('public')->path($miniature->front_image));
        $backUrl = imagecreatefromjpeg(Storage::disk('public')->path($miniature->back_image));

        imagecopymerge($outputImage, $frontUrl, 0, 0, 0, 0, $widthFront, $heightFront, 100);
        imagecopymerge($outputImage, $backUrl, $widthFront, 0, 0, 0, $widthBack, $heightBack, 100);

        $extension = 'jpg';
        $uuid = Str::uuid();
        $fileName = sprintf('%s_%s_combo.%s', $miniature->character_id, $uuid, $extension);
        $filePath = "characters/{$miniature->character_id}/{$fileName}";

        $path = Storage::disk('public')->path('/');
        imagejpeg($outputImage, $path.$filePath);
        $miniature->update(['combination_image' => $filePath]);
        imagedestroy($outputImage);
    }
}
