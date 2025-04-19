<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\SuitEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbilityAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/Abilities/Index', [
            'abilities' => Ability::all(),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/Abilities/AbilityForm', [
            'defensive_ability_types' => DefensiveAbilityTypeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function edit(Request $request, Ability $ability)
    {
        return inertia('Admin/Abilities/AbilityForm', [
            'ability' => $ability->loadMissing(['characters']),
            'defensive_ability_types' => DefensiveAbilityTypeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'characters' => Character::toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function store(Request $request)
    {
        $ability = $this->validateAndSave($request);

        return redirect()->route('admin.abilities.index')->withMessage("{$ability->name} created successfully.");
    }

    public function update(Request $request, Ability $ability)
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

    private function validateAndSave(Request $request, ?Ability $ability = null): Ability
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'suits' => ['nullable', 'string', 'max:255'],
            'defensive_ability_type' => ['nullable', 'string', Rule::enum(DefensiveAbilityTypeEnum::class)],
            'costs_stone' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
        ]);

        $characters = Character::whereIn('display_name', $validated['characters'])->get();
        unset($validated['characters']);

        if (! $ability) {
            $ability = Ability::create($validated);
            $ability->update([
                'slug' => $ability->id.'-'.$ability->slug,
            ]);
        } else {
            $ability->update($validated);
        }

        $ability->characters()->sync($characters->pluck('id'));

        return $ability;
    }
}
