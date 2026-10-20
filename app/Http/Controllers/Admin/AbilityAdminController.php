<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\GameModeTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AbilityRequest;
use App\Models\Ability;
use App\Models\Character;
use Illuminate\Http\Request;

class AbilityAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Abilities/Index', [
            'abilities' => Ability::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Abilities/AbilityForm', [
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Ability $ability)
    {
        return inertia('Admin/Abilities/AbilityForm', [
            'ability' => $ability->loadMissing(['characters']),
            'defensive_ability_types' => fn () => DefensiveAbilityTypeEnum::toSelectOptions(),
            'suits' => fn () => SuitEnum::toSelectOptions(),
            'characters' => fn () => Character::toSelectOptions('display_name', 'slug'),
            'game_mode_types' => fn () => GameModeTypeEnum::toSelectOptions(),
        ]);
    }

    public function store(AbilityRequest $request)
    {
        $ability = $this->validateAndSave($request);

        return redirect()->route('admin.abilities.index')->withMessage("{$ability->name} created successfully.");
    }

    public function update(AbilityRequest $request, Ability $ability)
    {
        $ability = $this->validateAndSave($request, $ability);

        return redirect()->route('admin.abilities.index')->withMessage("{$ability->name} has been updated.");
    }

    public function delete(Request $request, Ability $ability)
    {
        $name = $ability->name;
        $ability->delete();

        return redirect()->route('admin.abilities.index')->withMessage("{$name} has been deleted.");
    }

    private function validateAndSave(AbilityRequest $request, ?Ability $ability = null): Ability
    {
        $validated = $request->validated();

        $characters = Character::whereIn('display_name', $validated['characters'] ?? [])->get();
        unset($validated['characters']);

        // Slug is derived from the name by the UsesSlugName trait on every save.
        if (! $ability) {
            $ability = Ability::create($validated);
        } else {
            $ability->update($validated);
        }

        $ability->characters()->sync($characters->pluck('id'));

        return $ability;
    }
}
