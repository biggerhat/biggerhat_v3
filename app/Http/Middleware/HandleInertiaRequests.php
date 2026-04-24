<?php

namespace App\Http\Middleware;

use App\Enums\FactionEnum;
use App\Enums\TOS\AllegianceEnum;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'messageTitle' => fn () => $request->session()->get('messageTitle'),
                'messageType' => fn () => $request->session()->get('messageType'),
                'reset_link' => fn () => $request->session()->get('reset_link'),
            ],
            'faction_info' => FactionEnum::buildDetails(),
            'tos_allegiance_info' => AllegianceEnum::buildDetails(),
            'currentGameSystem' => $this->resolveGameSystem($request),
            'auth' => [
                'user' => $request->user() ?? null,
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
                'can_publish_posts' => $request->user()?->can('publish_posts'),
                'can_access_admin' => $this->canAccessAdmin($request),
                'is_super_admin' => (bool) $request->user()?->hasRole('super_admin'),
                'collection_miniature_ids' => fn () => $request->user()?->collectionMiniatures()->pluck('miniatures.id')->toArray() ?? [],
                'collection_package_ids' => fn () => $request->user()?->collectionPackages()->pluck('packages.id')->toArray() ?? [],
                'wishlists' => fn () => $request->user()?->wishlists()->select('id', 'name')->orderBy('name')->get() ?? [],
                'wishlist_items' => fn () => $this->getWishlistItems($request),
                'channel_ids' => fn () => $request->user()?->channels()->pluck('channels.id')->toArray() ?? [],
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /**
     * Resolve which game-system the user is currently looking at. URL is the
     * source of truth (anything under /tos or /admin/tos counts as TOS); on
     * game-agnostic routes (auth/profile/settings) we fall back to the
     * `preferred_game_system` cookie so a returning user lands back where they
     * left off.
     *
     * @return array{slug: string, label: string, home_route: string, switch_to: array{slug: string, label: string, home_route: string}}
     */
    private function resolveGameSystem(Request $request): array
    {
        $isTosUrl = $request->is('tos', 'tos/*', 'admin/tos/*');

        $slug = 'malifaux';
        if ($isTosUrl) {
            $slug = 'tos';
        } elseif (! $this->urlSpecifiesGameSystem($request) && $request->cookie('preferred_game_system') === 'tos') {
            $slug = 'tos';
        }

        $isTos = $slug === 'tos';

        return [
            'slug' => $slug,
            'label' => $isTos ? 'The Other Side' : 'Malifaux',
            'home_route' => $isTos ? route('tos.index') : route('index'),
            'switch_to' => $isTos
                ? ['slug' => 'malifaux', 'label' => 'Malifaux', 'home_route' => route('index')]
                : ['slug' => 'tos', 'label' => 'The Other Side', 'home_route' => route('tos.index')],
        ];
    }

    /**
     * Whether the current URL explicitly belongs to a game system (Malifaux or
     * TOS). Game-agnostic surfaces (auth, profile, settings) return false so
     * the cookie fallback can apply.
     */
    private function urlSpecifiesGameSystem(Request $request): bool
    {
        // TOS URLs already short-circuit higher up; everything we list here is
        // unambiguously Malifaux scaffolding.
        $malifauxPrefixes = [
            'characters', 'characters/*',
            'keywords', 'keywords/*',
            'markers', 'markers/*',
            'tokens', 'tokens/*',
            'actions', 'actions/*',
            'triggers', 'triggers/*',
            'abilities', 'abilities/*',
            'factions', 'factions/*',
            'upgrades/*',
            'packages', 'packages/*',
            'blueprints', 'blueprints/*',
            'lore', 'lore/*',
            'schemes/*', 'strategies/*', 'seasons', 'seasons/*',
            'advanced', 'advanced/*',
            'games', 'games/*',
            'tournaments', 'tournaments/*',
            'collection', 'collection/*',
            'wishlists', 'wishlists/*',
            'tools/*',
        ];

        if ($request->is(...$malifauxPrefixes)) {
            return true;
        }

        if ($request->is('tos', 'tos/*', 'admin/tos/*')) {
            return true;
        }

        return false;
    }

    private function canAccessAdmin(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasAnyPermission(EnsureHasAdminPermission::adminEntryPermissions());
    }

    /**
     * @return array<int, array{characters: int[], miniatures: int[], packages: int[]}>
     */
    private function getWishlistItems(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }

        $items = \App\Models\WishlistItem::query()
            ->whereIn('wishlist_id', $user->wishlists()->select('id'))
            ->select('wishlist_id', 'wishlistable_type', 'wishlistable_id')
            ->get();

        $result = [];
        foreach ($items as $item) {
            $wid = $item->wishlist_id;
            if (! isset($result[$wid])) {
                $result[$wid] = ['characters' => [], 'miniatures' => [], 'packages' => []];
            }

            $key = match ($item->wishlistable_type) {
                \App\Models\Character::class => 'characters',
                \App\Models\Miniature::class => 'miniatures',
                \App\Models\Package::class => 'packages',
                default => null,
            };

            if ($key) {
                $result[$wid][$key][] = $item->wishlistable_id;
            }
        }

        return $result;
    }
}
