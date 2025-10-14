<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Str;

class CrewAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Upgrades/Crews/Index', [
            'upgrades' => Upgrade::forCrews()->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Upgrades/Crews/UpgradeForm', [
            'characters' => Character::forStation(CharacterStationEnum::Master)->toSelectOptions('display_name', 'id'),
            'factions' => FactionEnum::toSelectOptions(),
            'keywords' => Keyword::toSelectOptions('name', 'id'),
            'tokens' => Token::all(),
            'markers' => Marker::all(),
            'actions' => Action::all()->map(function (Action $action) {
                return [
                    'slug' => $action->slug,
                    'name' => sprintf('%s %s %s', $action->id, $action->name, $action->internal_notes),
                ];
            }),
            'abilities' => Ability::all(),
            'triggers' => Trigger::all(),
        ]);
    }

    public function edit(Request $request, Upgrade $upgrade)
    {
        return inertia('Admin/Upgrades/Crews/UpgradeForm', [
            'upgrade' => $upgrade->loadMissing(['masters', 'tokens', 'markers', 'actions', 'abilities', 'triggers', 'keywords']),
            'characters' => Character::forStation(CharacterStationEnum::Master)->toSelectOptions('display_name', 'id'),
            'factions' => FactionEnum::toSelectOptions(),
            'keywords' => Keyword::toSelectOptions('name', 'id'),
            'tokens' => Token::all(),
            'markers' => Marker::all(),
            'actions' => Action::all()->map(function (Action $action) {
                return [
                    'slug' => $action->slug,
                    'name' => sprintf('%s %s %s', $action->id, $action->name, $action->internal_notes),
                ];
            }),
            'abilities' => Ability::all(),
            'triggers' => Trigger::all(),
        ]);
    }

    public function store(Request $request)
    {
        $upgrade = $this->validateAndSave($request);

        return redirect()->route('admin.crews.index')->withMessage("{$upgrade->name} created successfully.");
    }

    public function update(Request $request, Upgrade $upgrade)
    {
        $upgrade = $this->validateAndSave($request, $upgrade);

        return redirect()->route('admin.crews.index')->withMessage("{$upgrade->name} has been updated.");
    }

    public function delete(Request $request, Upgrade $upgrade)
    {
        $name = $upgrade->name;
        $upgrade->delete();

        return redirect()->route('admin.crews.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Upgrade $upgrade = null): Upgrade
    {
        $triggers = collect([]);
        $abilities = collect([]);
        $actions = collect([]);
        $characters = collect([]);
        $keywords = collect([]);
        $signatureActions = collect([]);
        $markers = collect([]);
        $tokens = collect([]);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'description' => ['nullable', 'string'],
            'power_bar_count' => ['nullable', 'integer'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:jpeg,jpg'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'tokens' => ['nullable', 'array'],
            'markers' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'signature_actions' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'triggers' => ['nullable', 'array'],
            'characters' => ['nullable', 'array'],
            'keywords' => ['nullable', 'array'],
        ]);

        $validated['domain'] = UpgradeDomainTypeEnum::Crew->value;

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

        if (isset($validated['actions'])) {
            $actionIds = [];
            foreach ($validated['actions'] as $action) {
                $arrayed = explode(' ', $action);
                $actionIds[] = $arrayed[0];
            }
            $actions = Action::whereIn('id', $actionIds)->get();
            unset($validated['actions']);
        }

        if (isset($validated['signature_actions'])) {
            $signatureActionIds = [];
            foreach ($validated['signature_actions'] as $action) {
                $arrayed = explode(' ', $action);
                $signatureActionIds[] = $arrayed[0];
            }
            $signatureActions = Action::whereIn('id', $signatureActionIds)->get();
            unset($validated['signature_actions']);
        }

        if (isset($validated['triggers'])) {
            $triggers = Trigger::whereIn('name', $validated['triggers'])->get();
            unset($validated['triggers']);
        }

        if (isset($validated['abilities'])) {
            $abilities = Ability::whereIn('name', $validated['abilities'])->get();
            unset($validated['abilities']);
        }

        if (isset($validated['markers'])) {
            $markers = Marker::whereIn('name', $validated['markers'])->get();
            unset($validated['markers']);
        }

        if (isset($validated['tokens'])) {
            $tokens = Token::whereIn('name', $validated['tokens'])->get();
            unset($validated['tokens']);
        }

        if (isset($validated['characters'])) {
            $characters = Character::whereIn('display_name', $validated['characters'])->get();
            unset($validated['characters']);
        }

        if (isset($validated['keywords'])) {
            $keywords = Keyword::whereIn('name', $validated['keywords'])->get();
            unset($validated['keywords']);
        }

        if (! $upgrade) {
            $upgrade = Upgrade::create($validated);
        } else {
            $upgrade->update($validated);
        }

        $upgrade->actions()->sync([]);
        $upgrade->actions()->attach($actions);
        $upgrade->actions()->attach($signatureActions, ['is_signature_action' => true]);

        $upgrade->triggers()->sync($triggers->pluck('id'));
        $upgrade->abilities()->sync($abilities->pluck('id'));
        $upgrade->markers()->sync($markers->pluck('id'));
        $upgrade->tokens()->sync($tokens->pluck('id'));
        $upgrade->characters()->sync($characters->pluck('id'));
        $upgrade->keywords()->sync($keywords->pluck('id'));

        if ($upgrade->front_image && $upgrade->back_image) {
            $this->generateComboImage($upgrade);
        } elseif ($upgrade->front_image && ! $upgrade->back_image) {
            $upgrade->update([
                'combination_image' => $upgrade->front_image,
            ]);
        }

        return $upgrade;
    }

    private function generateComboImage(Upgrade $upgrade)
    {
        [$widthFront, $heightFront] = getimagesize(Storage::disk('public')->path($upgrade->front_image));
        [$widthBack, $heightBack] = getimagesize(Storage::disk('public')->path($upgrade->back_image));
        $background = imagecreatetruecolor($widthFront + $widthBack, $heightFront);

        header('Content-Type: image/jpeg');
        $outputImage = $background;

        $frontUrl = imagecreatefromjpeg(Storage::disk('public')->path($upgrade->front_image));
        $backUrl = imagecreatefromjpeg(Storage::disk('public')->path($upgrade->back_image));

        imagecopymerge($outputImage, $frontUrl, 0, 0, 0, 0, $widthFront, $heightFront, 100);
        imagecopymerge($outputImage, $backUrl, $widthFront, 0, 0, 0, $widthBack, $heightBack, 100);

        $extension = 'jpg';
        $uuid = Str::uuid();
        $fileName = sprintf('%s_%s_combo.%s', $upgrade->slug, $uuid, $extension);
        $filePath = "upgrades/{$upgrade->slug}/{$fileName}";

        $path = Storage::disk('public')->path('/');
        imagejpeg($outputImage, $path.$filePath);
        $upgrade->update(['combination_image' => $filePath]);
        imagedestroy($outputImage);
    }
}
