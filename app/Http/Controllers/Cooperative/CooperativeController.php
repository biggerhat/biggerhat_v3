<?php

namespace App\Http\Controllers\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Cooperative\CoopClass;
use App\Models\Cooperative\CoopEncounter;
use Inertia\Response;
use Inertia\ResponseFactory;

/** Public reference pages for Cooperative Mode (Index of the Untold): the encounter list and the NP class cards. */
class CooperativeController extends Controller
{
    public function index(): Response|ResponseFactory
    {
        return inertia('Cooperative/Index', [
            'encounters' => CoopEncounter::published()->orderBy('number')
                ->get(['id', 'number', 'name', 'slug', 'encounter_size', 'max_hand_size', 'board_size', 'phases']),
            'classes' => CoopClass::published()->orderBy('sort_order')->orderBy('name')->get()
                ->groupBy(fn (CoopClass $c) => $c->role->value)
                ->map(fn ($group) => $group->values()),
            'roles' => CoopRoleEnum::toSelectOptions(),
        ]);
    }

    public function encounter(string $slug): Response|ResponseFactory
    {
        $encounter = CoopEncounter::published()->where('slug', $slug)->firstOrFail();
        $slugs = collect($encounter->phases ?? [])->flatMap(fn (array $p) => collect($p['role_order'] ?? [])->pluck('class_slug'))->filter()->unique();

        return inertia('Cooperative/Encounter', [
            'encounter' => $encounter,
            'classes' => CoopClass::published()->whereIn('slug', $slugs)->get()->keyBy('slug'),
            'neighbors' => [
                'previous' => CoopEncounter::published()->where('number', '<', $encounter->number)->orderByDesc('number')->first(['number', 'name', 'slug']),
                'next' => CoopEncounter::published()->where('number', '>', $encounter->number)->orderBy('number')->first(['number', 'name', 'slug']),
            ],
        ]);
    }
}
