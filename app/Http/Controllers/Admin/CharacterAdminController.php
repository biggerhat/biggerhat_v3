<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Miniature;
use App\Models\Token;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CharacterAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Characters/Index', [
            'characters' => Character::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Characters/CharacterForm', [
            'suits' => SuitEnum::toSelectOptions(),
            'factions' => FactionEnum::toSelectOptions(),
            'stations' => CharacterStationEnum::toSelectOptions(),
            'base_sizes' => BaseSizeEnum::toSelectOptions(),
            'keywords' => Keyword::toSelectOptions('name', 'slug'),
            'characteristics' => Characteristic::toSelectOptions('name', 'slug'),
            'miniatures' => Miniature::toSelectOptions('name', 'slug'),
            'actions' => Action::toSelectOptions('name', 'slug'),
            'abilities' => Ability::toSelectOptions('name', 'slug'),
            'markers' => Marker::toSelectOptions('name', 'slug'),
            'tokens' => Token::toSelectOptions('name', 'slug'),
            'totems' => Character::whereHas('characteristics', function (Builder $query) {
                $query->where('slug', 'totem');
            })->toSelectOptions('display_name', 'slug'),
            'crew_upgrades' => Upgrade::where('type', UpgradeTypeEnum::Crew->value)->toSelectOptions('name', 'slug'),
        ]);
    }

    public function edit(Request $request, Character $character)
    {
        return inertia('Admin/Characters/CharacterForm', [
            'character' => $character->loadMissing(['miniatures', 'keywords', 'actions', 'abilities', 'characteristics', 'markers', 'tokens', 'crewUpgrade', 'totem']),
            'suits' => SuitEnum::toSelectOptions(),
            'factions' => FactionEnum::toSelectOptions(),
            'stations' => CharacterStationEnum::toSelectOptions(),
            'base_sizes' => BaseSizeEnum::toSelectOptions(),
            'keywords' => Keyword::toSelectOptions('name', 'slug'),
            'characteristics' => Characteristic::toSelectOptions('name', 'slug'),
            'miniatures' => Miniature::toSelectOptions('name', 'slug'),
            'actions' => Action::toSelectOptions('name', 'slug'),
            'abilities' => Ability::toSelectOptions('name', 'slug'),
            'markers' => Marker::toSelectOptions('name', 'slug'),
            'tokens' => Token::toSelectOptions('name', 'slug'),
            'totems' => Character::whereHas('characteristics', function (Builder $query) {
                $query->where('slug', 'totem');
            })->toSelectOptions('display_name', 'slug'),
            'crew_upgrades' => Upgrade::where('type', UpgradeTypeEnum::Crew->value)->toSelectOptions('name', 'slug'),
        ]);
    }

    public function store(Request $request)
    {
        $character = $this->validateAndSave($request);

        return redirect()->route('admin.characters.index')->withMessage("{$character->name} created successfully.");
    }

    public function update(Request $request, Character $character)
    {
        $character = $this->validateAndSave($request, $character);

        return redirect()->route('admin.characters.index')->withMessage("{$character->name} has been updated.");
    }

    public function delete(Request $request, Character $character)
    {
        $name = $character->name;
        $character->delete();

        return redirect()->route('admin.characters.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(Request $request, ?Character $character = null)
    {
        $upgrade = null;
        $existingMiniatures = null;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'nicknames' => ['nullable', 'string', 'max:255'],
            'station' => ['required', 'string', Rule::enum(CharacterStationEnum::class)],
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'totem' => ['nullable', 'string'],
            'crew_upgrade' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array'],
            'characteristics' => ['nullable', 'array'],
            'miniatures' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'markers' => ['nullable', 'array'],
            'tokens' => ['nullable', 'array'],
            'cost' => ['required', 'integer'],
            'health' => ['required', 'integer'],
            'size' => ['required', 'integer'],
            'base' => ['required', 'integer', Rule::enum(BaseSizeEnum::class)],
            'speed' => ['required', 'integer'],
            'count' => ['required', 'integer'],
            'defense' => ['required', 'integer'],
            'defense_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'willpower' => ['required', 'integer'],
            'willpower_suit' => ['nullable', 'string', Rule::enum(SuitEnum::class)],
            'summon_target_number' => ['nullable', 'integer'],
            'generates_stone' => ['required', 'boolean'],
            'is_unhirable' => ['required', 'boolean'],
            'is_beta' => ['required', 'boolean'],
            'is_hidden' => ['required', 'boolean'],
        ]);

        $validated['display_name'] = $validated['name'];
        if (isset($validated['title'])) {
            $validated['display_name'] .= ", {$validated['title']}";
        }
        $validated['slug'] = Str::slug($validated['display_name']);

        if ($validated['summon_target_number'] === 0) {
            unset($validated['summon_target_number']);
        }

        if ($validated['totem']) {
            $totem = Character::where('slug', $validated['totem'])->first();
            $validated['has_totem_id'] = $totem->id;
        }
        unset($validated['totem']);

        if ($validated['crew_upgrade']) {
            $upgrade = Upgrade::where('slug', $validated['crew_upgrade'])->first();
        }
        unset($validated['crew_upgrade']);

        $keywords = Keyword::whereIn('name', $validated['keywords'])->get();
        unset($validated['keywords']);

        $characteristics = Characteristic::whereIn('name', $validated['characteristics'])->get();
        unset($validated['characteristics']);

        $actions = Action::whereIn('id', $validated['actions'])->get();
        unset($validated['actions']);

        $abilities = Ability::whereIn('name', $validated['abilities'])->get();
        unset($validated['abilities']);

        $markers = Marker::whereIn('name', $validated['markers'])->get();
        unset($validated['markers']);

        $tokens = Token::whereIn('name', $validated['tokens'])->get();
        unset($validated['tokens']);

        if (! ($character)) {
            $character = Character::create($validated);
        } else {
            $character->update($validated);
        }

        if ($upgrade) {
            $upgrade->master_id = $character->id;
            $upgrade->save();
        }

        $character->keywords()->sync($keywords->pluck('id'));
        $character->characteristics()->sync($characteristics->pluck('id'));
        $character->actions()->sync($actions->pluck('id'));
        $character->abilities()->sync($abilities->pluck('id'));
        $character->markers()->sync($markers->pluck('id'));
        $character->tokens()->sync($tokens->pluck('id'));

        return $character;
    }
}
