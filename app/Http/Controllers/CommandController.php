<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $characters = Character::with('miniatures')->get()->filter(function (Character $character) {
            return $character->miniatures->count() > 0;
        });
        $characters = $characters->map(function (Character $character) {
            return [
                'name' => $character->display_name,
                'route' => route('characters.view', ['character' => $character->slug, 'miniature' => $character->miniatures->first()->id, 'slug' => $character->miniatures->first()->slug]),
            ];
        });
        $factions = collect(FactionEnum::cases())->map(function (FactionEnum $faction) {
            return [
                'name' => $faction->label(),
                'route' => route('factions.view', ['factionEnum' => $faction->value]),
            ];
        });
        $keywords = Keyword::all()->map(function (Keyword $keyword) {
            return [
                'name' => $keyword->name,
                'route' => route('keywords.view', ['keyword' => $keyword->slug]),
            ];
        });

        return response()->json([
            'factions' => $factions,
            'keywords' => $keywords,
            'characters' => $characters,
        ]);
    }
}
