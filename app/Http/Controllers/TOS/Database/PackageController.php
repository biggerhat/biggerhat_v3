<?php

namespace App\Http\Controllers\TOS\Database;

use App\Enums\GameSystemEnum;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\TOS\Allegiance;
use App\Models\TOS\Unit;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $nameSearch = $request->filled('name_search') ? trim((string) $request->get('name_search')) : null;
        $allegiance = $request->filled('allegiance') ? (string) $request->get('allegiance') : null;
        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $query = Package::withCount('tosUnits')
            ->whereIn('game_system', [GameSystemEnum::Tos, GameSystemEnum::Both])
            ->orderBy('name', 'ASC');

        if ($nameSearch) {
            $query->where('name', 'LIKE', "%{$nameSearch}%");
        }

        if ($allegiance) {
            $query->whereHas('tosUnits.allegiances', fn ($q) => $q->where('tos_allegiances.slug', $allegiance));
        }

        return inertia('TOS/Packages/Index', [
            'packages' => $query->paginate($perPage)->withQueryString(),
            'name_search' => $nameSearch,
            'allegiance_filter' => $allegiance,
            'page_view' => $pageView,
            'allegiances' => fn () => Allegiance::orderBy('name')->get(['id', 'slug', 'name']),
        ]);
    }

    public function view(Package $package)
    {
        abort_if(! in_array($package->game_system, [GameSystemEnum::Tos, GameSystemEnum::Both], true), 404);

        $package->loadMissing(['tosUnits.sides', 'tosUnits.allegiances', 'tosUnits.sculpts', 'storeLinks']);

        return inertia('TOS/Packages/View', [
            'package' => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'description' => $package->description,
                'sku' => $package->sku,
                'upc' => $package->upc,
                'msrp' => $package->msrp,
                'front_image' => $package->front_image,
                'back_image' => $package->back_image,
                'combination_image' => $package->combination_image,
                'is_preassembled' => $package->is_preassembled,
                'released_at' => $package->released_at,
                'units' => $package->tosUnits->map(fn (Unit $u) => [
                    'name' => $u->name,
                    'slug' => $u->slug,
                    'quantity' => $u->pivot->quantity ?? 1,
                    'allegiances' => $u->allegiances->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name]),
                    'first_sculpt_slug' => $u->sculpts->first()?->slug,
                ]),
                'store_links' => $package->storeLinks->map(fn ($link) => [
                    'store_name' => $link->store_name,
                    'url' => $link->url,
                ]),
            ],
        ]);
    }
}
