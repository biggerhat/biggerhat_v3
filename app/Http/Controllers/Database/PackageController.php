<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::with(['characters', 'miniatures', 'keywords'])
            ->orderBy('name', 'ASC');

        if ($request->get('faction')) {
            $query->whereJsonContains('factions', $request->get('faction'));
        }

        $packages = $query->get()->map(function (Package $package) {
            return [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'factions' => collect($package->factions ?? [])->map(fn (string $f) => [
                    'value' => $f,
                    'label' => FactionEnum::from($f)->label(),
                    'color' => FactionEnum::from($f)->color(),
                    'logo' => FactionEnum::from($f)->logo(),
                ]),
                'front_image' => $package->front_image,
                'combination_image' => $package->combination_image,
                'sku' => $package->sku,
                'msrp' => $package->msrp,
                'sculpt_version' => $package->sculpt_version,
                'sculpt_version_label' => SculptVersionEnum::from($package->sculpt_version)->label(),
                'released_at' => $package->released_at,
                'characters_count' => $package->characters->count(),
                'miniatures_count' => $package->miniatures->count(),
            ];
        });

        return inertia('Packages/Index', [
            'packages' => $packages,
            'factions' => FactionEnum::buildDetails(),
        ]);
    }

    public function view(Request $request, Package $package)
    {
        $package->load(['characters.standardMiniatures', 'miniatures', 'keywords']);

        return inertia('Packages/View', [
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'description' => $package->description,
                'factions' => collect($package->factions ?? [])->map(fn (string $f) => [
                    'value' => $f,
                    'label' => FactionEnum::from($f)->label(),
                    'color' => FactionEnum::from($f)->color(),
                    'logo' => FactionEnum::from($f)->logo(),
                ]),
                'sku' => $package->sku,
                'upc' => $package->upc,
                'msrp' => $package->msrp,
                'distributor_description' => $package->distributor_description,
                'front_image' => $package->front_image,
                'back_image' => $package->back_image,
                'combination_image' => $package->combination_image,
                'sculpt_version' => $package->sculpt_version,
                'sculpt_version_label' => SculptVersionEnum::from($package->sculpt_version)->label(),
                'is_preassembled' => $package->is_preassembled,
                'released_at' => $package->released_at,
                'characters' => $package->characters->map(fn (Character $c) => [
                    'display_name' => $c->display_name,
                    'slug' => $c->slug,
                    'faction' => $c->faction->value,
                    'faction_color' => $c->faction->color(),
                ]),
                'miniatures' => $package->miniatures->map(fn (Miniature $m) => [
                    'display_name' => $m->display_name,
                    'slug' => $m->slug,
                ]),
                'keywords' => $package->keywords->map(fn (Keyword $k) => [
                    'name' => $k->name,
                    'slug' => $k->slug,
                ]),
            ],
        ]);
    }
}
