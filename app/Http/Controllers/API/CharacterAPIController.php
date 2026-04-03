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

        $characters = Character::where('display_name', 'LIKE', "%{$q}%")
            ->orWhere('name', 'LIKE', "%{$q}%")
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

    public function images(Request $request)
    {
        $storageUrl = config('filesystems.disks.public.url').'/';

        return Character::whereHas('miniatures')
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
