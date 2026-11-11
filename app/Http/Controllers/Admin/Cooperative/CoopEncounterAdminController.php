<?php

namespace App\Http\Controllers\Admin\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cooperative\CoopEncounterRequest;
use App\Models\Cooperative\CoopClass;
use App\Models\Cooperative\CoopEncounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class CoopEncounterAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Encounters/Index', [
            'items' => CoopEncounter::query()->orderBy('number')->get(['id', 'number', 'name', 'encounter_size', 'max_hand_size', 'is_published']),
        ]);
    }

    public function create(Request $request): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Encounters/Form', [
            'item' => null,
            'next_number' => (int) CoopEncounter::max('number') + 1,
            ...$this->options(),
        ]);
    }

    public function edit(Request $request, CoopEncounter $coopEncounter): Response|ResponseFactory
    {
        return inertia('Admin/Cooperative/Encounters/Form', ['item' => $coopEncounter, 'next_number' => null, ...$this->options()]);
    }

    public function store(CoopEncounterRequest $request): RedirectResponse
    {
        $row = CoopEncounter::create($request->validated());

        return redirect()->route('admin.cooperative.encounters.index')->withMessage("{$row->name} created.");
    }

    public function update(CoopEncounterRequest $request, CoopEncounter $coopEncounter): RedirectResponse
    {
        $coopEncounter->update($request->validated());

        return redirect()->route('admin.cooperative.encounters.index')->withMessage("{$coopEncounter->name} updated.");
    }

    public function delete(Request $request, CoopEncounter $coopEncounter): RedirectResponse
    {
        $name = $coopEncounter->name;
        $coopEncounter->delete();

        return redirect()->route('admin.cooperative.encounters.index')->withMessage("{$name} deleted.");
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'roles' => CoopRoleEnum::toSelectOptions(),
            'classes' => CoopClass::query()->orderBy('role')->orderBy('name')->get(['slug', 'name', 'role'])
                ->map(fn (CoopClass $c) => ['slug' => $c->slug, 'name' => $c->name, 'role' => $c->role->value]),
        ];
    }
}
