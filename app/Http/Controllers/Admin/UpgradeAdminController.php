<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterStationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Marker;
use App\Models\Token;
use App\Models\Trigger;
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
            'upgrades' => Upgrade::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Upgrades/UpgradeForm', [
            'characters' => Character::where('station', CharacterStationEnum::Master->value)->toSelectOptions('display_name', 'id'),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
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
        return inertia('Admin/Upgrades/UpgradeForm', [
            'upgrade' => $upgrade->loadMissing(['master', 'tokens', 'markers', 'actions', 'abilities', 'triggers']),
            'characters' => Character::where('station', CharacterStationEnum::Master->value)->toSelectOptions('display_name', 'id'),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
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
        $triggers = [];
        $abilities = [];
        $actions = [];
        $markers = [];
        $tokens = [];

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(UpgradeTypeEnum::class)],
            'master_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'power_bar_count' => ['nullable', 'integer'],
            'plentiful' => ['nullable', 'integer'],
            'limitations' => ['nullable', 'string'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'tokens' => ['nullable', 'array'],
            'markers' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'triggers' => ['nullable', 'array'],
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

        if (isset($validated['actions'])) {
            $actionIds = [];
            foreach ($validated['actions'] as $action) {
                $arrayed = explode(' ', $action);
                $actionIds[] = $arrayed[0];
            }
            $actions = Action::whereIn('id', $actionIds)->get();
            unset($validated['actions']);
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


        if (! $upgrade) {
            $upgrade = Upgrade::create($validated);
        } else {
            $upgrade->update($validated);
        }

        $upgrade->triggers()->sync($triggers->pluck('id'));
        $upgrade->abilities()->sync($abilities->pluck('id'));
        $upgrade->actions()->sync($actions->pluck('id'));
        $upgrade->markers()->sync($markers->pluck('id'));
        $upgrade->tokens()->sync($tokens->pluck('id'));

        return $upgrade;
    }
}
