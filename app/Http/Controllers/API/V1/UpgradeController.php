<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\UpgradeResource;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * @tags Upgrades
 */
class UpgradeController extends Controller
{
    /**
     * List all upgrades
     *
     * Returns a paginated list of upgrades, optionally filtered by domain, faction, type, or name.
     * When searching crew upgrades (`domain=crew`), the search term is also matched against linked
     * character names (display_name and nicknames) so you can find a crew upgrade by its master.
     *
     * @queryParam search string Filter upgrades by name (or character name for crew domain). Example: Soulstone Cache
     * @queryParam domain string Filter by upgrade domain (character or crew). Example: character
     * @queryParam faction string Filter by faction enum value. Example: arcanists
     * @queryParam type string Filter by upgrade type. Example: limited
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $domain = $request->query('domain');
        $search = $request->query('search');

        $upgrades = Upgrade::query()
            ->when($domain, fn ($q, $d) => $q->where('domain', $d))
            ->when($request->query('faction'), fn ($q, $faction) => $q->where('faction', $faction))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($search, function ($q) use ($search, $domain) {
                $q->where(function ($inner) use ($search, $domain) {
                    $inner->where('name', 'LIKE', "%{$search}%");
                    if ($domain === 'crew') {
                        $inner->orWhereHas('characters', function ($charQuery) use ($search) {
                            $charQuery->where('display_name', 'LIKE', "%{$search}%")
                                ->orWhere('nicknames', 'LIKE', "%{$search}%");
                        });
                    }
                });
            })
            ->when($domain === 'crew', fn ($q) => $q->with('characters'));

        if ($search) {
            $upgrades->orderBy(DB::raw('CASE
                WHEN LOWER(name) = LOWER(?) THEN 0
                WHEN LOWER(name) LIKE LOWER(?) THEN 1
                ELSE 2
            END'))->addBinding([$search, $search.'%'], 'order');
        }

        $upgrades = $upgrades
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return UpgradeResource::collection($upgrades);
    }

    /**
     * Get a single upgrade
     *
     * Returns a single upgrade with its associated keywords, actions, triggers, abilities, markers, tokens, and characters.
     */
    public function show(Upgrade $upgrade): UpgradeResource
    {
        $upgrade->loadMissing(['keywords', 'actions.triggers', 'abilities', 'triggers', 'markers', 'tokens', 'characters']);

        return new UpgradeResource($upgrade);
    }
}
