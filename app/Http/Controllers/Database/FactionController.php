<?php

namespace App\Http\Controllers\Database;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Characteristic;
use App\Models\Keyword;
use Illuminate\Http\Request;

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        $query = Character::with('keywords', 'standardMiniatures')->whereHas('standardMiniatures')->where('faction', $factionEnum->value);

        $keywords = Keyword::whereHas('characters', function ($query) use ($factionEnum) {
            $query->where('faction', $factionEnum->value);
        })->get();

        $characteristics = Characteristic::whereHas('characters', function ($query) use ($factionEnum) {
            $query->where('faction', $factionEnum->value);
        })->get();

        if ($request->get('keyword')) {
            $query->whereHas('keywords', function ($query) use ($request) {
                $query->where('slug', $request->get('keyword'));
            });
        }

        if ($request->get('station')) {
            $query->where('station', $request->get('station'));
        }

        if ($request->get('characteristic')) {
            $query->whereHas('characteristics', function ($query) use ($request) {
                $query->where('slug', $request->get('characteristic'));
            });
        }

        $characters = $query->get();

        $miniatures = 0;

        $characters->each(function (Character $character) use (&$miniatures) {
            $miniatures += $character->count;
        });

        $stats = [
            'characters' => $characters->count(),
            'miniatures' => $miniatures,
            'keywords' => $keywords->count(),
        ];

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url').$factionEnum->logo(), 'route' => $factionEnum->value],
            'characters' => $characters,
            'keywords' => $keywords,
            'characteristics' => $characteristics,
            'statistics' => $stats,
            'stations' => CharacterStationEnum::toSelectOptions(),
        ]);
    }
}
