<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Enums\GameSystemEnum;
use App\Enums\PackageCategoryEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Concerns\BuildsPageMeta;
use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    use BuildsPageMeta;

    public function index(Request $request)
    {
        $query = Package::withCount(['characters', 'miniatures'])
            ->whereIn('game_system', [GameSystemEnum::Malifaux, GameSystemEnum::Both])
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

    /**
     * A single flat, searchable reference of every box's contents — the
     * counterpart to browsing/viewing packages one at a time above. Small
     * enough to ship as one payload (a few hundred rows) so search/filter
     * runs client-side with no extra round trips.
     */
    public function contents()
    {
        $packages = Package::whereIn('game_system', [GameSystemEnum::Malifaux, GameSystemEnum::Both])
            ->whereHas('characters')
            ->with('characters.standardMiniatures', 'characters.keywords')
            ->orderBy('name')
            ->get()
            ->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'legacy_m3e_name' => $package->legacy_m3e_name,
                'category' => $package->category?->value,
                'category_label' => $package->category?->label(),
                'msrp' => $package->msrp,
                'released_at' => $package->released_at,
                'is_auto_generated' => $package->is_auto_generated,
                'is_standard_edition' => in_array(SculptVersionEnum::from($package->sculpt_version), SculptVersionEnum::standardEditions(), true),
                'characters' => $package->characters->map(fn (Character $c) => [
                    'display_name' => $c->display_name,
                    'slug' => $c->slug,
                    'faction' => $c->faction->value,
                    'faction_label' => $c->faction->label(),
                    'faction_color' => $c->faction->color(),
                    'quantity' => $c->pivot->quantity ?? 1,
                    'special_order' => (bool) ($c->pivot->special_order ?? false),
                    'keywords' => $c->keywords->pluck('name'),
                    'standard_miniature' => $c->standardMiniatures->first() ? [
                        'id' => $c->standardMiniatures->first()->id,
                        'slug' => $c->standardMiniatures->first()->slug,
                        'display_name' => $c->standardMiniatures->first()->display_name,
                        'front_image' => $c->standardMiniatures->first()->front_image,
                        'back_image' => $c->standardMiniatures->first()->back_image,
                        'character_id' => $c->id,
                    ] : null,
                ])->sortBy('display_name')->values(),
            ]);

        return inertia('Packages/Contents', [
            'packages' => $packages,
            'factions' => fn () => FactionEnum::buildDetails(),
            'categories' => fn () => PackageCategoryEnum::toSelectOptions(),
            'keywords' => fn () => Keyword::toSelectOptions('name', 'name'),
        ]);
    }

    public function view(Request $request, Package $package)
    {
        abort_if(! in_array($package->game_system, [GameSystemEnum::Malifaux, GameSystemEnum::Both], true), 404);

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
                    'special_order' => (bool) ($c->pivot->special_order ?? false),
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
        ])->withViewData([
            'page_meta' => $this->pageMeta(
                title: $package->name,
                description: $package->description ?: $this->summarizePackage($package),
                image: $package->front_image,
            ),
        ]);
    }

    /**
     * Falls back to a short content summary when a package has no description —
     * keeps social previews from being empty for store / starter boxes.
     */
    private function summarizePackage(Package $package): string
    {
        $factionLabels = collect($package->factions ?? [])
            ->map(fn (string $f) => FactionEnum::from($f)->label())
            ->implode(', ');
        $characterCount = $package->characters->count();

        $parts = [];
        if ($factionLabels !== '') {
            $parts[] = $factionLabels.' package';
        }
        if ($characterCount > 0) {
            $parts[] = $characterCount.' '.($characterCount === 1 ? 'character' : 'characters');
        }
        if ($package->category) {
            $parts[] = $package->category->label();
        }

        return $parts ? implode(' · ', $parts) : 'Malifaux package';
    }
}
