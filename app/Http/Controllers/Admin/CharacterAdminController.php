<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
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
use Illuminate\Validation\Rule;

class CharacterAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Characters/Index', [
            'characters' => Character::orderBy('display_name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Characters/CharacterForm', $this->getFormData());
    }

    public function edit(Request $request, Character $character)
    {
        return inertia('Admin/Characters/CharacterForm', array_merge(
            ['character' => $character->loadMissing(['miniatures', 'keywords', 'actions', 'abilities', 'characteristics', 'markers', 'tokens', 'crewUpgrades', 'totem', 'summons', 'replacesInto', 'replacesOnDeath'])],
            $this->getFormData(),
        ));
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

    private function getFormData(): array
    {
        return [
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'factions' => fn () => FactionEnum::toSelectOptions(),
            'stations' => fn () => CharacterStationEnum::toSelectOptions(),
            'base_sizes' => fn () => BaseSizeEnum::toSelectOptions(),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'slug'),
            'characteristics' => fn () => Characteristic::toSelectOptions('name', 'slug'),
            'miniatures' => fn () => Miniature::toSelectOptions('name', 'slug'),
            'actions' => fn () => Action::all()->map(function (Action $action) {
                return [
                    'slug' => $action->slug,
                    'name' => sprintf('%s %s %s', $action->id, $action->name, $action->internal_notes),
                ];
            }),
            'abilities' => fn () => Ability::toSelectOptions('name', 'slug'),
            'markers' => fn () => Marker::toSelectOptions('name', 'slug'),
            'tokens' => fn () => Token::toSelectOptions('name', 'slug'),
            'totems' => fn () => Character::whereHas('characteristics', function (Builder $query) {
                $query->where('slug', 'totem');
            })->toSelectOptions('display_name', 'slug'),
            'crew_upgrades' => fn () => Upgrade::forCrews()->toSelectOptions('name', 'slug'),
            'crew_upgrade_modes' => fn () => \App\Enums\CrewUpgradeModeEnum::toSelectOptions(),
            'all_characters' => fn () => Character::orderBy('display_name')->toSelectOptions('display_name', 'slug'),
        ];
    }

    private function validateAndSave(Request $request, ?Character $character = null)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'nicknames' => ['nullable', 'string', 'max:255'],
            'station' => ['nullable', 'string', Rule::enum(CharacterStationEnum::class)],
            'faction' => ['required', 'string', Rule::enum(FactionEnum::class)],
            'totem' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array'],
            'characteristics' => ['nullable', 'array'],
            'miniatures' => ['nullable', 'array'],
            'abilities' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'signature_actions' => ['nullable', 'array'],
            'markers' => ['nullable', 'array'],
            'tokens' => ['nullable', 'array'],
            'cost' => ['nullable', 'integer'],
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
            'crew_upgrade_mode' => ['nullable', 'string', Rule::enum(\App\Enums\CrewUpgradeModeEnum::class)],
            'is_beta' => ['required', 'boolean'],
            'is_hidden' => ['required', 'boolean'],
            'summons' => ['nullable', 'array'],
            'summons.*' => ['string'],
            'replaces_into' => ['nullable', 'array'],
            'replaces_into.*' => ['string'],
            'replaces_on_death' => ['nullable', 'array'],
            'replaces_on_death.*.slug' => ['required', 'string'],
            'replaces_on_death.*.count' => ['required', 'integer', 'min:1'],
            'replaces_on_death.*.health' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['station']) {
            $stationEnum = CharacterStationEnum::from($validated['station']);
            $validated['station_sort_order'] = $stationEnum->sortOrder();
        } else {
            $validated['station_sort_order'] = CharacterStationEnum::NON_STATION_SORT_ORDER;
        }

        if ($validated['summon_target_number'] === 0) {
            unset($validated['summon_target_number']);
        }

        if ($validated['totem']) {
            $totem = Character::where('slug', $validated['totem'])->first();
            $validated['has_totem_id'] = $totem->id;
        }
        unset($validated['totem']);

        $keywords = Keyword::whereIn('name', $validated['keywords'])->get();
        unset($validated['keywords']);

        $characteristics = Characteristic::whereIn('name', $validated['characteristics'])->get();
        unset($validated['characteristics']);

        $actionIds = [];
        foreach ($validated['actions'] as $action) {
            $arrayed = explode(' ', $action);
            $actionIds[] = $arrayed[0];
        }
        $actions = Action::whereIn('id', $actionIds)->get();
        unset($validated['actions']);

        $signatureActionIds = [];
        foreach ($validated['signature_actions'] as $action) {
            $arrayed = explode(' ', $action);
            $signatureActionIds[] = $arrayed[0];
        }
        $signatureActions = Action::whereIn('id', $signatureActionIds)->get();
        unset($validated['signature_actions']);

        $abilities = Ability::whereIn('name', $validated['abilities'])->get();
        unset($validated['abilities']);

        $markers = Marker::whereIn('name', $validated['markers'])->get();
        unset($validated['markers']);

        $tokens = Token::whereIn('name', $validated['tokens'])->get();
        unset($validated['tokens']);

        $summonSync = $this->buildLinkSync($validated['summons'] ?? [], 'summons');
        unset($validated['summons']);

        $replacesIntoSync = $this->buildLinkSync($validated['replaces_into'] ?? [], 'replaces_into');
        unset($validated['replaces_into']);

        $replacesOnDeathSync = $this->buildLinkSync($validated['replaces_on_death'] ?? [], 'replaces_on_death');
        unset($validated['replaces_on_death']);

        if (! ($character)) {
            $character = Character::create($validated);
        } else {
            $character->update($validated);
        }

        // Detach all Current Actions Then Attach All News Ones, including Signature
        $character->actions()->sync([]);
        $character->actions()->attach($actions);
        $character->actions()->attach($signatureActions, ['is_signature_action' => true]);

        $character->keywords()->sync($keywords->pluck('id'));
        $character->characteristics()->sync($characteristics->pluck('id'));
        $character->abilities()->sync($abilities->pluck('id'));
        $character->markers()->sync($markers->pluck('id'));
        $character->tokens()->sync($tokens->pluck('id'));

        $character->summons()->sync($summonSync);
        $character->replacesInto()->sync($replacesIntoSync);
        $character->replacesOnDeath()->sync($replacesOnDeathSync);

        return $character;
    }

    /**
     * Build a sync array for character_links from either simple slug strings or {slug, count} objects.
     *
     * @return array<int, array{type: string, count: int}>
     */
    private function buildLinkSync(array $items, string $type): array
    {
        $sync = [];
        $slugs = [];
        $countMap = [];
        $healthMap = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $slugs[] = $item;
            } elseif (is_array($item) && isset($item['slug'])) {
                $slugs[] = $item['slug'];
                $countMap[$item['slug']] = $item['count'] ?? 1;
                if (isset($item['health']) && $item['health']) {
                    $healthMap[$item['slug']] = (int) $item['health'];
                }
            }
        }

        if (empty($slugs)) {
            return [];
        }

        $characters = Character::whereIn('slug', $slugs)->get();
        foreach ($characters as $char) {
            $sync[$char->id] = [
                'type' => $type,
                'count' => $countMap[$char->slug] ?? 1,
                'health' => $healthMap[$char->slug] ?? null,
            ];
        }

        return $sync;
    }
}
