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
        $characters = Character::with('keywords', 'standardMiniatures')->whereHas('standardMiniatures')->where('faction', $factionEnum->value)->get();

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
            'keywords' => $keywords->flatten()->unique()->count(),
        ];

        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color(), 'logo' => config('app.url') . $factionEnum->logo()],
            'characters' => $characters,
            'keywords' => $keywords->flatten()->unique(),
            'statistics' => $stats,
            'stations' => CharacterStationEnum::toSelectOptions(),
        ]);
    }
}
