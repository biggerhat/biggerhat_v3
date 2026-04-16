<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Enums\PackageCategoryEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::withCount(['characters', 'miniatures'])
            ->orderBy('name', 'ASC');

        if ($request->filled('name_search')) {
            $query->where('name', 'LIKE', '%'.$request->get('name_search').'%');
        }

        if ($request->filled('faction')) {
            $query->whereJsonContains('factions', $request->get('faction'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->get('category'));
        }

        if ($request->filled('sculpt_version')) {
            $query->where('sculpt_version', $request->get('sculpt_version'));
        }

        if ($request->filled('character')) {
            $query->whereHas('characters', function ($q) use ($request) {
                $q->where('characters.slug', $request->get('character'));
            });
        }

        if ($request->filled('keyword')) {
            $query->whereHas('keywords', function ($q) use ($request) {
                $q->where('keywords.slug', $request->get('keyword'));
            });
        }

        $pageView = $request->get('page_view', 'cards');
        $perPage = $pageView === 'table' ? 50 : 24;

        $packages = $query->paginate($perPage)->withQueryString()->through(function (Package $package) {
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
                'category' => $package->category?->value,
                'category_label' => $package->category?->label(),
                'sculpt_version' => $package->sculpt_version,
                'sculpt_version_label' => SculptVersionEnum::from($package->sculpt_version)->label(),
                'released_at' => $package->released_at,
                'characters_count' => $package->characters_count,
                'miniatures_count' => $package->miniatures_count,
            ];
        });

        return inertia('Packages/Index', [
            'packages' => $packages,
            'result_count' => $packages->total(),
            'factions' => fn () => FactionEnum::buildDetails(),
            'categories' => fn () => PackageCategoryEnum::toSelectOptions(),
            'sculpt_versions' => fn () => SculptVersionEnum::toSelectOptions(),
            'characters' => fn () => Character::standard()->toSelectOptions('display_name', 'slug'),
        ]);
    }

    public function view(Request $request, Package $package)
    {
        $package->load(['characters.standardMiniatures', 'miniatures', 'keywords', 'storeLinks', 'blueprints' => fn ($q) => $q->withImage()]);

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
                'category' => $package->category?->value,
                'category_label' => $package->category?->label(),
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
                    'quantity' => $c->pivot->quantity ?? 1,
                    'standard_miniature' => $c->standardMiniatures->first() ? [
                        'id' => $c->standardMiniatures->first()->id,
                        'slug' => $c->standardMiniatures->first()->slug,
                    ] : null,
                ]),
                'miniatures' => $package->miniatures->map(fn (Miniature $m) => [
                    'display_name' => $m->display_name,
                    'slug' => $m->slug,
                ]),
                'keywords' => $package->keywords->map(fn (Keyword $k) => [
                    'name' => $k->name,
                    'slug' => $k->slug,
                ]),
                'store_links' => $package->storeLinks->map(fn ($link) => [
                    'store_name' => $link->store_name,
                    'url' => $link->url,
                ]),
                'blueprints' => $package->blueprints->map(fn (Blueprint $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                    'image_path' => $b->image_path,
                    'source_url' => $b->source_url,
                    'sculpt_version' => $b->sculpt_version->value,
                ]),
            ],
        ]);
    }
}
