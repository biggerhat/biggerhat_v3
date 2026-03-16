<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Upgrade;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function crewIndex(Request $request)
    {
        $upgrades = Upgrade::forCrews()
            ->with(['masters', 'keywords'])
            ->orderBy('name', 'ASC')
            ->get()
            ->map(function (Upgrade $upgrade) {
                return [
                    'id' => $upgrade->id,
                    'name' => $upgrade->name,
                    'slug' => $upgrade->slug,
                    'faction' => $upgrade->faction?->value,
                    'faction_label' => $upgrade->faction?->label(),
                    'faction_color' => $upgrade->faction?->color(),
                    'faction_logo' => $upgrade->faction?->logo(),
                    'front_image' => $upgrade->front_image,
                    'back_image' => $upgrade->back_image,
                    'combination_image' => $upgrade->combination_image,
                    'masters' => $upgrade->masters->map(fn ($m) => [
                        'display_name' => $m->display_name,
                        'slug' => $m->slug,
                    ]),
                ];
            });

        return inertia('Upgrades/CrewIndex', [
            'upgrades' => $upgrades,
            'factions' => FactionEnum::buildDetails(),
        ]);
    }

    public function characterIndex(Request $request)
    {
        $upgrades = Upgrade::forCharacters()
            ->with(['masters', 'keywords', 'characters'])
            ->orderBy('name', 'ASC')
            ->get()
            ->map(function (Upgrade $upgrade) {
                return [
                    'id' => $upgrade->id,
                    'name' => $upgrade->name,
                    'slug' => $upgrade->slug,
                    'type' => $upgrade->type?->value,
                    'type_label' => $upgrade->type?->label(),
                    'faction' => $upgrade->faction?->value,
                    'faction_label' => $upgrade->faction?->label(),
                    'faction_color' => $upgrade->faction?->color(),
                    'faction_logo' => $upgrade->faction?->logo(),
                    'limitations' => $upgrade->limitations?->value,
                    'limitations_label' => $upgrade->limitations?->label(),
                    'front_image' => $upgrade->front_image,
                    'back_image' => $upgrade->back_image,
                    'combination_image' => $upgrade->combination_image,
                    'characters_count' => $upgrade->characters->count(),
                    'masters' => $upgrade->masters->map(fn ($m) => [
                        'display_name' => $m->display_name,
                        'slug' => $m->slug,
                    ]),
                ];
            });

        return inertia('Upgrades/CharacterIndex', [
            'upgrades' => $upgrades,
            'factions' => FactionEnum::buildDetails(),
            'types' => UpgradeTypeEnum::toSelectOptions(),
        ]);
    }

    public function view(Request $request, Upgrade $upgrade)
    {
        $upgrade->load(['masters.standardMiniatures', 'keywords', 'characters.standardMiniatures', 'actions', 'abilities', 'triggers', 'markers', 'tokens']);

        return inertia('Upgrades/View', [
            'upgrade' => [
                'id' => $upgrade->id,
                'name' => $upgrade->name,
                'slug' => $upgrade->slug,
                'domain' => $upgrade->domain->value,
                'domain_label' => $upgrade->domain->label(),
                'type' => $upgrade->type?->value,
                'type_label' => $upgrade->type?->label(),
                'faction' => $upgrade->faction?->value,
                'faction_label' => $upgrade->faction?->label(),
                'faction_color' => $upgrade->faction?->color(),
                'faction_logo' => $upgrade->faction?->logo(),
                'limitations' => $upgrade->limitations?->value,
                'limitations_label' => $upgrade->limitations?->label(),
                'front_image' => $upgrade->front_image,
                'back_image' => $upgrade->back_image,
                'combination_image' => $upgrade->combination_image,
                'plentiful' => $upgrade->plentiful,
                'masters' => $upgrade->masters->map(fn ($m) => [
                    'display_name' => $m->display_name,
                    'slug' => $m->slug,
                    'faction' => $m->faction->value,
                    'miniature_id' => $m->standardMiniatures->first()?->id,
                    'miniature_slug' => $m->standardMiniatures->first()?->slug,
                ]),
                'keywords' => $upgrade->keywords->map(fn ($k) => [
                    'name' => $k->name,
                    'slug' => $k->slug,
                ]),
                'characters' => $upgrade->characters->map(fn ($c) => [
                    'display_name' => $c->display_name,
                    'slug' => $c->slug,
                    'faction' => $c->faction->value,
                    'miniature_id' => $c->standardMiniatures->first()?->id,
                    'miniature_slug' => $c->standardMiniatures->first()?->slug,
                ]),
                'actions' => $upgrade->actions->map(fn ($a) => [
                    'name' => $a->name,
                    'slug' => $a->slug,
                ]),
                'abilities' => $upgrade->abilities->map(fn ($a) => [
                    'name' => $a->name,
                    'slug' => $a->slug,
                ]),
                'triggers' => $upgrade->triggers->map(fn ($t) => [
                    'name' => $t->name,
                    'slug' => $t->slug,
                ]),
                'markers' => $upgrade->markers->map(fn ($m) => [
                    'name' => $m->name,
                    'slug' => $m->slug ?? null,
                ]),
                'tokens' => $upgrade->tokens->map(fn ($t) => [
                    'name' => $t->name,
                    'slug' => $t->slug ?? null,
                ]),
            ],
        ]);
    }
}
