<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\Upgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $characters = Character::standard()->with('miniatures')->whereHas('miniatures')->orderBy('display_name', 'ASC')->get();

        $characters = $characters->map(function (Character $character) {
            return [
                'name' => $character->display_name,
                'route' => route('characters.view', ['character' => $character->slug, 'miniature' => $character->miniatures->first()->id, 'slug' => $character->miniatures->first()->slug]),
            ];
        });

        $upgrades = Upgrade::standard()->orderBy('name', 'ASC')->get()->map(function (Upgrade $upgrade) {
            return [
                'name' => $upgrade->name,
                'route' => route('upgrades.view', ['upgrade' => $upgrade->slug]),
            ];
        });

        $factions = collect(FactionEnum::cases())->map(function (FactionEnum $faction) {
            return [
                'name' => $faction->label(),
                'route' => route('factions.view', ['factionEnum' => $faction->value]),
            ];
        });

        $keywords = Keyword::standard()->orderBy('name', 'ASC')->get()->map(function (Keyword $keyword) {
            return [
                'name' => $keyword->name,
                'route' => route('keywords.view', ['keyword' => $keyword->slug]),
            ];
        });

        $packages = Package::orderBy('name', 'ASC')->get()->map(function (Package $package) {
            return [
                'name' => $package->name,
                'route' => route('packages.view', ['package' => $package->slug]),
            ];
        });

        $promoMiniatures = Miniature::with('character')
            ->whereIn('version', collect(SculptVersionEnum::promotionalEditions())->map->value)
            ->whereHas('character', fn ($q) => $q->where('is_hidden', false))
            ->orderBy('display_name', 'ASC')
            ->get()
            ->map(function (Miniature $mini) {
                return [
                    'name' => $mini->display_name,
                    'route' => route('characters.view', [
                        'character' => $mini->character->slug,
                        'miniature' => $mini->id,
                        'slug' => $mini->slug,
                    ]),
                ];
            });

        return response()->json([
            'factions' => $factions,
            'keywords' => $keywords,
            'characters' => $characters,
            'miniatures' => $promoMiniatures,
            'upgrades' => $upgrades,
            'packages' => $packages,
        ]);
    }
}
