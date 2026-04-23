<?php

namespace App\Http\Middleware;

use App\Enums\FactionEnum;
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
            'auth' => [
                'user' => $request->user() ?? null,
                'permissions' => $request->user()?->getAllPermissions()->pluck('name') ?? [],
                'can_publish_posts' => $request->user()?->can('publish_posts'),
                'can_access_admin' => $this->canAccessAdmin($request),
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
