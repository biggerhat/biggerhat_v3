<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;

class CharacterAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        $miniatures = Miniature::where('display_name', $name)
            ->orWhereHas('character', function ($query) use ($name) {
                $query->where('nicknames', $name);
            })
            ->with('character')->get();

        if ($miniatures->count() === 0) {
            $miniatures = Miniature::where('display_name', 'LIKE', "%{$name}%")
                ->orWhereHas('character', function ($query) use ($name) {
                    $query->where('nicknames', 'LIKE', "%{$name}%");
                })
                ->with('character')->get();
        }

        return $miniatures->unique('character_id');
    }

    public function search(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $characters = Character::standard()->where(function ($query) use ($q) {
            $query->where('display_name', 'LIKE', "%{$q}%")
                ->orWhere('name', 'LIKE', "%{$q}%");
        })
            ->with('miniatures')
            ->limit(15)
            ->get();

        return $characters->map(fn (Character $c) => [ // @phpstan-ignore return.type, argument.type
            'id' => $c->id,
            'display_name' => $c->display_name,
            'slug' => $c->slug,
            'faction' => $c->getRawOriginal('faction'),
            'station' => $c->station?->value,
            'count' => $c->count ?? 1,
            'summon_target_number' => $c->summon_target_number,
            'front_image' => ($firstMini = $c->miniatures->first())?->front_image
                ? '/storage/'.$firstMini->front_image
                : null,
            'miniatures' => $c->miniatures->map(fn ($m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
                'front_image' => $m->front_image,
                'back_image' => $m->back_image,
            ]),
        ]);
    }

    public function miniatures(int $characterId)
    {
        $character = Character::with('miniatures')->findOrFail($characterId);

        return response()->json($character->miniatures->map(fn (Miniature $m) => [
            'id' => $m->id,
            'display_name' => $m->display_name,
            'front_image' => $m->front_image,
            'back_image' => $m->back_image,
        ]));
    }

    public function compare(Request $request): \Illuminate\Http\JsonResponse
    {
        $slugs = array_filter(explode(',', $request->get('slugs', '')));
        $slugs = array_slice($slugs, 0, 4);

        if (empty($slugs)) {
            return response()->json([]);
        }

        $characters = Character::whereIn('slug', $slugs)
            ->with(['miniatures', 'keywords', 'characteristics', 'actions.triggers', 'abilities', 'crewUpgrades'])
            ->get();

        $result = [];
        foreach ($characters as $c) {
            $result[] = [
                'id' => $c->id,
                'display_name' => $c->display_name,
                'name' => $c->name,
                'title' => $c->title,
                'slug' => $c->slug,
                'faction' => $c->getRawOriginal('faction'),
                'station' => $c->station?->value,
                'cost' => $c->cost,
                'health' => $c->health,
                'defense' => $c->defense,
                'defense_suit' => $c->getRawOriginal('defense_suit'),
                'willpower' => $c->willpower,
                'willpower_suit' => $c->getRawOriginal('willpower_suit'),
                'speed' => $c->speed,
                'size' => $c->size,
                'base' => $c->base?->value, // @phpstan-ignore nullsafe.neverNull
                'count' => $c->count,
                'miniature_id' => $c->miniatures->first()?->id,
                'miniature_slug' => $c->miniatures->first()?->slug,
                'front_image' => $c->miniatures->first()?->front_image,
                'back_image' => $c->miniatures->first()?->back_image,
                'combination_image' => $c->miniatures->first()?->combination_image,
                'keywords' => $c->keywords->map(fn ($k) => ['name' => $k->name, 'slug' => $k->slug])->values()->all(),
                'characteristics' => $c->characteristics->pluck('name')->values()->all(),
                'actions' => $c->actions->map(fn ($a) => [
                    'name' => $a->name,
                    'type' => $a->getRawOriginal('type'),
                    'stat' => $a->stat,
                    'stat_suits' => $a->stat_suits,
                    'stat_modifier' => $a->getRawOriginal('stat_modifier'),
                    'range' => $a->range,
                    'range_type' => $a->getRawOriginal('range_type'),
                    'resisted_by' => $a->resisted_by,
                    'target_number' => $a->target_number,
                    'target_suits' => $a->target_suits,
                    'damage' => $a->damage,
                    'description' => $a->description,
                    'is_signature' => (bool) ($a->pivot->is_signature_action ?? false),
                    'stone_cost' => $a->stone_cost,
                    'triggers' => $a->triggers->map(fn ($t) => [
                        'name' => $t->name,
                        'suits' => $t->suits,
                        'stone_cost' => $t->stone_cost,
                        'description' => $t->description,
                    ])->values()->all(),
                ])->values()->all(),
                'abilities' => $c->abilities->map(fn ($a) => [
                    'name' => $a->name,
                    'suits' => $a->suits,
                    'description' => $a->description,
                    'defensive_ability_type' => $a->getRawOriginal('defensive_ability_type'),
                    'costs_stone' => $a->costs_stone,
                ])->values()->all(),
            ];
        }

        return response()->json($result);
    }

    public function images(Request $request)
    {
        $storageUrl = config('filesystems.disks.public.url').'/';

        return Character::standard()->whereHas('miniatures')
            ->with(['miniatures', 'crewUpgrades'])
            ->orderBy('display_name', 'ASC')
            ->get()
            ->map(function (Character $character) use ($storageUrl) {
                /** @var Miniature $firstMiniature */
                $firstMiniature = $character->miniatures->first();

                $characterInfo = [
                    'display_name' => $character->display_name,
                    'front_image' => $storageUrl.$firstMiniature->front_image,
                    'back_image' => $storageUrl.$firstMiniature->back_image,
                    'combination_image' => $storageUrl.$firstMiniature->combination_image,
                    'url' => route('characters.view', [
                        'character' => $character->slug,
                        'miniature' => $firstMiniature->id,
                        'slug' => $firstMiniature->slug,
                    ]),
                ];

                if ($character->crewUpgrades) {
                    foreach ($character->crewUpgrades as $crewUpgrade) {
                        $characterInfo['crew_upgrades'][] = [
                            'name' => $crewUpgrade->name,
                            'front_image' => $storageUrl.$crewUpgrade->front_image,
                            'back_image' => $storageUrl.$crewUpgrade->back_image,
                            'combination_image' => $storageUrl.$crewUpgrade->combination_image,
                        ];
                    }

                }

                return $characterInfo;
            });
    }
}
