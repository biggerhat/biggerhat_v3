<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CharacterRequest;
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
use Illuminate\Support\Facades\DB;

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

    public function store(CharacterRequest $request)
    {
        $character = $this->validateAndSave($request);

        return redirect()->route('admin.characters.index')->withMessage("{$character->name} created successfully.");
    }

    public function update(CharacterRequest $request, Character $character)
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
            'actions' => fn () => Action::orderBy('name')->get(['id', 'name', 'internal_notes'])->map(fn (Action $action) => [
                'id' => $action->id,
                'name' => $action->name.' (#'.$action->id.')'.($action->internal_notes ? ' - '.$action->internal_notes : ''),
            ]),
            'abilities' => fn () => Ability::toSelectOptions('name', 'slug'),
            'markers' => fn () => Marker::toSelectOptions('name', 'slug'),
            'tokens' => fn () => Token::toSelectOptions('name', 'slug'),
            'totems' => fn () => Character::whereHas('characteristics', function (Builder $query) {
                $query->where('slug', 'totem');
            })->toSelectOptions('display_name', 'slug'),
            'crew_upgrades' => fn () => Upgrade::forCrews()->toSelectOptions('name', 'slug'),
            'crew_upgrade_modes' => fn () => \App\Enums\CrewUpgradeModeEnum::toSelectOptions(),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
            'all_characters' => fn () => Character::orderBy('display_name')->toSelectOptions('display_name', 'slug'),
        ];
    }

    private function validateAndSave(CharacterRequest $request, ?Character $character = null)
    {
        $validated = $request->validated();

        if ($validated['station']) {
            $stationEnum = CharacterStationEnum::from($validated['station']);
            $validated['station_sort_order'] = $stationEnum->sortOrder();
        } else {
            $validated['station_sort_order'] = CharacterStationEnum::NON_STATION_SORT_ORDER;
        }

        if ($validated['summon_target_number'] === 0) {
            unset($validated['summon_target_number']);
        }

        // Clearing the totem field must also clear the stored relation. The
        // slug is validated with `exists`, so the lookup can't come back null.
        $validated['has_totem_id'] = ($validated['totem'] ?? null)
            ? Character::where('slug', $validated['totem'])->value('id')
            : null;
        unset($validated['totem']);

        $keywords = Keyword::whereIn('name', $validated['keywords'] ?? [])->get();
        unset($validated['keywords']);

        $characteristics = Characteristic::whereIn('name', $validated['characteristics'] ?? [])->get();
        unset($validated['characteristics']);

        $actions = Action::whereIn('id', $validated['actions'] ?? [])->get();
        unset($validated['actions']);

        $signatureActions = Action::whereIn('id', $validated['signature_actions'] ?? [])->get();
        unset($validated['signature_actions']);

        $abilities = Ability::whereIn('name', $validated['abilities'] ?? [])->get();
        unset($validated['abilities']);

        $markers = Marker::whereIn('name', $validated['markers'] ?? [])->get();
        unset($validated['markers']);

        $tokens = Token::whereIn('name', $validated['tokens'] ?? [])->get();
        unset($validated['tokens']);

        $summonSync = $this->buildLinkSync($validated['summons'] ?? [], 'summons');
        unset($validated['summons']);

        $replacesIntoSync = $this->buildLinkSync($validated['replaces_into'] ?? [], 'replaces_into');
        unset($validated['replaces_into']);

        $replacesOnDeathSync = $this->buildLinkSync($validated['replaces_on_death'] ?? [], 'replaces_on_death');
        unset($validated['replaces_on_death']);

        // Model write + relation syncs land together or not at all.
        return DB::transaction(function () use ($validated, $character, $actions, $signatureActions, $keywords, $characteristics, $abilities, $markers, $tokens, $summonSync, $replacesIntoSync, $replacesOnDeathSync) {
            if (! ($character)) {
                $character = Character::create($validated);
            } else {
                $character->update($validated);
            }

            // Signature actions and main actions come from two independent
            // multiselects, so the same action id can appear in both — attaching
            // both collections separately would insert a duplicate pivot row for
            // it (no unique constraint catches this). Merge into a single sync
            // map keyed by action id instead, unioning both sets so an action
            // marked signature-only (not also picked in the main list) still
            // gets attached, matching the previous two-attach behavior.
            $signatureActionIds = $signatureActions->pluck('id')->all();
            $actionSync = $actions->concat($signatureActions)->unique('id')->mapWithKeys(fn (Action $action) => [
                $action->id => ['is_signature_action' => in_array($action->id, $signatureActionIds, true)],
            ]);
            $character->actions()->sync($actionSync);

            $character->keywords()->sync($keywords->pluck('id'));
            $character->characteristics()->sync($characteristics->pluck('id'));
            $character->abilities()->sync($abilities->pluck('id'));
            $character->markers()->sync($markers->pluck('id'));
            $character->tokens()->sync($tokens->pluck('id'));

            $character->summons()->sync($summonSync);
            $character->replacesInto()->sync($replacesIntoSync);
            $character->replacesOnDeath()->sync($replacesOnDeathSync);

            return $character;
        });
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
