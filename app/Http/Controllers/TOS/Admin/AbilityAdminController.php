<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Enums\TOS\UsageLimitEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAbilityRequest;
use App\Http\Requests\TOS\Admin\UpdateAbilityRequest;
use App\Models\TOS\Ability;
use App\Models\TOS\Allegiance;
use Illuminate\Http\Request;

class AbilityAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/Abilities/Index', [
            // Explicit column list — index table doesn't need the full `body`
            // text or timestamps; keeps the payload light.
            'abilities' => Ability::with('allegiance:id,name')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'is_general', 'allegiance_id', 'usage_limit']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Abilities/AbilityForm', [
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name']),
            'usage_limits' => fn () => UsageLimitEnum::toSelectOptions(),
        ]);
    }

    public function edit(Request $request, Ability $ability)
    {
        return inertia('Admin/TOS/Abilities/AbilityForm', [
            'ability' => $ability,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'name']),
            'usage_limits' => fn () => UsageLimitEnum::toSelectOptions(),
        ]);
    }

    public function store(StoreAbilityRequest $request)
    {
        $ability = Ability::create($request->validated());

        return redirect()->route('admin.tos.abilities.index')->withMessage("{$ability->name} created.");
    }

    public function update(UpdateAbilityRequest $request, Ability $ability)
    {
        $ability->update($request->validated());

        return redirect()->route('admin.tos.abilities.index')->withMessage("{$ability->name} updated.");
    }

    public function delete(Request $request, Ability $ability)
    {
        $name = $ability->name;
        $ability->delete();

        return redirect()->route('admin.tos.abilities.index')->withMessage("{$name} deleted.");
    }
}
