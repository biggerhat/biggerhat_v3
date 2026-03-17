<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Characteristic;
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
            'upgrades' => Upgrade::forCrews()->with('characters')->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Upgrades/Crews/UpgradeForm', $this->getFormData());
    }

    public function edit(Request $request, Upgrade $upgrade)
    {
        $upgrade->loadMissing(['characters', 'tokens', 'markers', 'actions', 'abilities', 'triggers', 'keywords']);

        // Decompose hiring_rules JSON into individual form fields
        $hiringRules = $upgrade->hiring_rules;
        $decomposed = [
            'hiring_rules_type' => null,
            'alternate_leader' => null,
            'any_faction' => false,
            'fixed_crew_keyword' => null,
            'fixed_cache' => null,
            'required_characteristic' => null,
            'required_count' => null,
        ];

        if ($hiringRules) {
            if (isset($hiringRules['fixed_crew_keyword'])) {
                $decomposed['hiring_rules_type'] = 'fixed_crew';
                $decomposed['alternate_leader'] = $hiringRules['alternate_leader_id'] ?? null;
                $decomposed['any_faction'] = $hiringRules['any_faction'] ?? false;
                $decomposed['fixed_crew_keyword'] = $hiringRules['fixed_crew_keyword'] ?? null;
                $decomposed['fixed_cache'] = $hiringRules['fixed_cache'] ?? null;
            } elseif (isset($hiringRules['required_characteristic'])) {
                $decomposed['hiring_rules_type'] = 'required_hires';
                $decomposed['required_characteristic'] = $hiringRules['required_characteristic'] ?? null;
                $decomposed['required_count'] = $hiringRules['required_count'] ?? null;
            }
        }

        return inertia('Admin/Upgrades/Crews/UpgradeForm', array_merge(
            ['upgrade' => $upgrade, 'hiring_rules_fields' => $decomposed],
            $this->getFormData(),
        ));
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

    private function getFormData(): array
    {
        return [
            'characters' => fn () => Character::forStation(CharacterStationEnum::Master)->toSelectOptions('display_name', 'id'),
            'all_characters' => fn () => Character::where('is_hidden', false)->orderBy('display_name')->get(['id', 'display_name'])->map(fn ($c) => ['value' => $c->id, 'name' => $c->display_name]),
            'characteristics' => fn () => Characteristic::orderBy('name')->get(['id', 'name', 'slug'])->map(fn ($c) => ['value' => $c->slug, 'name' => $c->name]),
            'factions' => fn () => FactionEnum::toSelectOptions(),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'id'),
            'tokens' => fn () => Token::all(),
            'markers' => fn () => Marker::all(),
            'actions' => fn () => Action::all()->map(function (Action $action) {
                return [
                    'slug' => $action->slug,
                    'name' => sprintf('%s %s %s', $action->id, $action->name, $action->internal_notes),
                ];
            }),
            'abilities' => fn () => Ability::all(),
            'triggers' => fn () => Trigger::all(),
        ];
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
            'hiring_rules_type' => ['nullable', 'string', 'in:fixed_crew,required_hires'],
            'alternate_leader' => ['nullable', 'integer', 'exists:characters,id'],
            'any_faction' => ['nullable'],
            'fixed_crew_keyword' => ['nullable', 'string'],
            'fixed_cache' => ['nullable', 'integer', 'min:0'],
            'required_characteristic' => ['nullable', 'string'],
            'required_count' => ['nullable', 'integer', 'min:1'],
        ]);

        // Build hiring_rules JSON from subfields
        $hiringRulesType = $validated['hiring_rules_type'] ?? null;
        unset($validated['hiring_rules_type'], $validated['alternate_leader'], $validated['any_faction'], $validated['fixed_crew_keyword'], $validated['fixed_cache'], $validated['required_characteristic'], $validated['required_count']);

        if ($hiringRulesType === 'fixed_crew') {
            $validated['hiring_rules'] = array_filter([
                'alternate_leader_id' => $request->input('alternate_leader') ? (int) $request->input('alternate_leader') : null,
                'any_faction' => (bool) $request->input('any_faction'),
                'fixed_crew_keyword' => $request->input('fixed_crew_keyword'),
                'fixed_cache' => $request->input('fixed_cache') !== null ? (int) $request->input('fixed_cache') : null,
            ], fn ($v) => $v !== null && $v !== false);
        } elseif ($hiringRulesType === 'required_hires') {
            $validated['hiring_rules'] = array_filter([
                'required_characteristic' => $request->input('required_characteristic'),
                'required_count' => $request->input('required_count') !== null ? (int) $request->input('required_count') : null,
            ], fn ($v) => $v !== null);
        } else {
            $validated['hiring_rules'] = null;
        }

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
