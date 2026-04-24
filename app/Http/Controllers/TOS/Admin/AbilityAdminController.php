<?php

namespace App\Http\Controllers\TOS\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TOS\Admin\StoreAbilityRequest;
use App\Http\Requests\TOS\Admin\UpdateAbilityRequest;
use App\Models\TOS\Ability;
use Illuminate\Http\Request;

class AbilityAdminController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Admin/TOS/Abilities/Index', [
            // Explicit column list — index table doesn't need the full `body`
            // text or timestamps; keeps the payload light.
            'abilities' => Ability::orderBy('name')->get(['id', 'slug', 'name', 'is_general']),
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Admin/TOS/Abilities/AbilityForm');
    }

    public function edit(Request $request, Ability $ability)
    {
        return inertia('Admin/TOS/Abilities/AbilityForm', [
            'ability' => $ability,
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
