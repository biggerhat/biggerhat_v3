<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Package;
use App\Models\TOS\Unit;
use App\Models\TOS\UnitSculpt;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WishlistController extends Controller
{
    public function index()
    {
        $wishlists = Auth::user()->wishlists()
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return inertia('Wishlists/Index', [
            'wishlists' => $wishlists,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $wishlist = Auth::user()->wishlists()->create($validated);

        return redirect()->route('wishlists.show', $wishlist);
    }

    public function show(Wishlist $wishlist)
    {
        $this->authorize('view', $wishlist);

        return inertia('Wishlists/Show', [
            ...$this->buildWishlistData($wishlist),
            'is_owner' => Auth::id() === $wishlist->user_id,
        ]);
    }

    public function share(string $shareCode)
    {
        $wishlist = Wishlist::where('share_code', $shareCode)->firstOrFail();

        // viewShare honors the `is_public` flag and supports anonymous viewers
        // — the `share` route lives outside auth middleware, unlike `show`.
        abort_unless(Gate::allows('viewShare', $wishlist), 403, 'This wishlist is private.');

        return inertia('Wishlists/Show', [
            ...$this->buildWishlistData($wishlist),
            'is_owner' => Auth::id() === $wishlist->user_id,
            'owner_name' => $wishlist->user->name,
        ]);
    }

    public function update(Request $request, Wishlist $wishlist)
    {
        $this->authorize('update', $wishlist);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $wishlist->update($validated);

        return back();
    }

    public function destroy(Wishlist $wishlist)
    {
        $this->authorize('delete', $wishlist);

        $wishlist->delete();

        return redirect()->route('wishlists.index');
    }

    public function addItem(Request $request, Wishlist $wishlist)
    {
        $this->authorize('update', $wishlist);

        $validated = $request->validate([
            'type' => 'required|in:character,miniature,package,unit,unit_sculpt',
            'id' => 'required|integer',
        ]);

        $modelMap = [
            'character' => Character::class,
            'miniature' => Miniature::class,
            'package' => Package::class,
            'unit' => Unit::class,
            'unit_sculpt' => UnitSculpt::class,
        ];

        $modelClass = $modelMap[$validated['type']];
        $model = $modelClass::findOrFail($validated['id']);

        WishlistItem::firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'wishlistable_type' => $modelClass,
            'wishlistable_id' => $model->id,
        ]);

        return back();
    }

    public function removeItem(Wishlist $wishlist, WishlistItem $wishlistItem)
    {
        $this->authorize('update', $wishlist);
        abort_unless($wishlistItem->wishlist_id === $wishlist->id, 404);

        $wishlistItem->delete();

        return back();
    }

    public function addKeyword(Request $request, Wishlist $wishlist)
    {
        $this->authorize('update', $wishlist);

        $validated = $request->validate([
            'keyword_id' => 'required|exists:keywords,id',
        ]);

        $keyword = Keyword::standard()->with(['characters' => function ($q) {
            $q->standard()->where('is_hidden', false)
                ->with('packages');
        }])->findOrFail($validated['keyword_id']);

        DB::transaction(function () use ($wishlist, $keyword) {
            foreach ($keyword->characters as $character) {
                WishlistItem::firstOrCreate([
                    'wishlist_id' => $wishlist->id,
                    'wishlistable_type' => Character::class,
                    'wishlistable_id' => $character->id,
                ]);

                foreach ($character->packages as $package) {
                    WishlistItem::firstOrCreate([
                        'wishlist_id' => $wishlist->id,
                        'wishlistable_type' => Package::class,
                        'wishlistable_id' => $package->id,
                    ]);
                }
            }
        });

        return back();
    }

    public function togglePublic(Wishlist $wishlist)
    {
        $this->authorize('update', $wishlist);

        $wishlist->update(['is_public' => ! $wishlist->is_public]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWishlistData(Wishlist $wishlist): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WishlistItem> $items */
        $items = $wishlist->items()->with(['wishlistable' => function ($morphTo) {
            $morphTo->morphWith([
                Character::class => ['standardMiniatures'],
                Miniature::class => ['character'],
                Unit::class => ['sculpts', 'allegiances'],
                UnitSculpt::class => ['unit.allegiances'],
            ]);
        }])->get();

        $grouped = [
            'characters' => [],
            'miniatures' => [],
            'packages' => [],
            'units' => [],
            'unit_sculpts' => [],
        ];

        foreach ($items as $item) {
            /** @var Character|Miniature|Package|Unit|UnitSculpt|null $model */
            $model = $item->wishlistable;
            if (! $model) {
                continue;
            }

            if ($model instanceof Character) {
                /** @var Miniature|null $standardMini */
                $standardMini = $model->standardMiniatures->first();
                $grouped['characters'][] = [
                    'item_id' => $item->id,
                    'id' => $model->id,
                    'name' => $model->display_name,
                    'slug' => $model->slug,
                    'faction' => $model->faction->value,
                    'faction_label' => $model->faction->label(),
                    'faction_color' => $model->faction->color(),
                    'faction_logo' => $model->faction->logo(),
                    'station' => $model->station?->value,
                    'station_label' => $model->station?->label(),
                    'front_image' => $standardMini?->front_image,
                    'standard_miniature_id' => $standardMini?->id,
                    'notes' => $item->notes,
                ];
            } elseif ($model instanceof Miniature) {
                $grouped['miniatures'][] = [
                    'item_id' => $item->id,
                    'id' => $model->id,
                    'name' => $model->display_name,
                    'slug' => $model->slug,
                    'character_name' => $model->character->display_name,
                    'character_slug' => $model->character->slug,
                    'front_image' => $model->front_image,
                    'notes' => $item->notes,
                ];
            } elseif ($model instanceof Package) {
                $grouped['packages'][] = [
                    'item_id' => $item->id,
                    'id' => $model->id,
                    'name' => $model->name,
                    'slug' => $model->slug,
                    'front_image' => $model->front_image,
                    'factions' => collect($model->factions ?? [])->map(fn (string $f) => [
                        'value' => $f,
                        'label' => FactionEnum::from($f)->label(),
                        'color' => FactionEnum::from($f)->color(),
                        'logo' => FactionEnum::from($f)->logo(),
                    ])->toArray(),
                    'notes' => $item->notes,
                ];
            } elseif ($model instanceof Unit) {
                /** @var UnitSculpt|null $firstSculpt */
                $firstSculpt = $model->sculpts->first();
                $allegiance = $model->allegiances->first();
                $grouped['units'][] = [
                    'item_id' => $item->id,
                    'id' => $model->id,
                    'name' => $model->name,
                    'slug' => $model->slug,
                    'allegiance' => $allegiance?->name,
                    'allegiance_slug' => $allegiance?->slug,
                    'front_image' => $firstSculpt?->front_image,
                    'first_sculpt_slug' => $firstSculpt?->slug,
                    'notes' => $item->notes,
                ];
            } elseif ($model instanceof UnitSculpt) {
                $grouped['unit_sculpts'][] = [
                    'item_id' => $item->id,
                    'id' => $model->id,
                    'name' => $model->name ?? $model->unit->name,
                    'slug' => $model->slug,
                    'unit_name' => $model->unit->name,
                    'unit_slug' => $model->unit->slug,
                    'front_image' => $model->front_image,
                    'notes' => $item->notes,
                ];
            }
        }

        return [
            'wishlist' => [
                'id' => $wishlist->id,
                'name' => $wishlist->name,
                'share_code' => $wishlist->share_code,
                'is_public' => $wishlist->is_public,
            ],
            'items' => $grouped,
            'keywords' => Keyword::standard()->orderBy('name')->get(['id', 'name', 'slug']),
            'searchable' => [
                'characters' => Character::standard()->where('is_hidden', false)
                    ->orderBy('display_name')
                    ->get(['id', 'display_name', 'slug', 'faction'])
                    ->map(fn (Character $c) => [
                        'id' => $c->id,
                        'name' => $c->display_name,
                        'faction' => $c->faction->label(),
                    ]),
                'miniatures' => Miniature::orderBy('display_name')
                    ->get(['id', 'display_name'])
                    ->map(fn (Miniature $m) => [
                        'id' => $m->id,
                        'name' => $m->display_name,
                    ]),
                'packages' => Package::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Package $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                    ]),
                'units' => Unit::notCombinedArmsChild()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Unit $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                    ]),
                'unit_sculpts' => UnitSculpt::with('unit:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'unit_id'])
                    ->map(fn (UnitSculpt $s) => [
                        'id' => $s->id,
                        'name' => $s->name ?? $s->unit->name,
                    ]),
            ],
        ];
    }
}
