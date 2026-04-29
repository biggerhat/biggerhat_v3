<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\TOS\Allegiance as TosAllegiance;
use App\Models\TOS\Stratagem as TosStratagem;
use App\Models\TOS\Unit as TosUnit;
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

        $tosAllegiances = TosAllegiance::orderBy('name', 'ASC')->get()->map(function (TosAllegiance $a) {
            return [
                'name' => $a->name,
                'route' => route('tos.allegiances.view', ['allegiance' => $a->slug]),
            ];
        });

        // Units view by first sculpt slug (rulebook cards are indexed on the
        // Unit but the public URL opens a specific sculpt). Skip any Unit
        // without a sculpt — those have no browseable page yet.
        $tosUnits = TosUnit::with(['sculpts' => fn ($q) => $q->orderBy('sort_order')])
            ->notCombinedArmsChild()
            ->orderBy('name', 'ASC')
            ->get()
            ->filter(fn (TosUnit $u) => $u->sculpts->isNotEmpty())
            ->map(function (TosUnit $u) {
                return [
                    'name' => $u->name.($u->title ? ", {$u->title}" : ''),
                    'route' => route('tos.units.view', ['sculpt' => $u->sculpts->first()->slug]),
                ];
            })
            ->values();

        $tosStratagems = TosStratagem::orderBy('name', 'ASC')->get()->map(function (TosStratagem $s) {
            return [
                'name' => $s->name,
                'route' => route('tos.stratagems.view', ['stratagem' => $s->slug]),
            ];
        });

        return response()->json([
            'factions' => $factions,
            'keywords' => $keywords,
            'characters' => $characters,
            'miniatures' => $promoMiniatures,
            'upgrades' => $upgrades,
            'packages' => $packages,
            'tos_allegiances' => $tosAllegiances,
            'tos_units' => $tosUnits,
            'tos_stratagems' => $tosStratagems,
        ]);
    }
}
