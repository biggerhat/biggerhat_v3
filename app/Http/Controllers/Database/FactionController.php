<?php

namespace App\Http\Controllers\Database;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use Illuminate\Http\Request;

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        $query = Character::with('keywords', 'standardMiniatures')->whereHas('standardMiniatures')->where('faction', $factionEnum->value);

        if ($request->get('keyword')) {
            $query->whereHas('keywords', function ($query) use ($request) {
                $query->where('slug', $request->get('keyword'));
            });
        }

        if ($request->get('station')) {
            $query->where('station', $request->get('station'));
        }

        $characters = $query->get();

        $keywords = collect([]);
        $miniatures = 0;

        $characters->each(function (Character $character) use ($keywords, &$miniatures) {
            $miniatures += $character->count;
            if ($character->keywords->count() > 0) {
                $keywords->push($character->keywords);
            }
        });

        $stats = [
            'characters' => $characters->count(),
            'miniatures' => $miniatures,
            'keywords' => $keywords->flatten()->unique('name')->count(),
        ];

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url').$factionEnum->logo(), 'route' => $factionEnum->value],
            'characters' => $characters,
            'keywords' => $keywords->flatten()->unique('name'),
            'statistics' => $stats,
            'stations' => CharacterStationEnum::toSelectOptions(),
        ]);
    }
}
