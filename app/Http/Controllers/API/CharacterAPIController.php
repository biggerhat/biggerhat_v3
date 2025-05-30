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

        return $miniatures;
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
