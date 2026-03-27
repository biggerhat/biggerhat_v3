<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\UpgradeResource;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Upgrades
 */
class UpgradeController extends Controller
{
    /**
     * List all upgrades
     *
     * Returns a paginated list of upgrades, optionally filtered by domain, faction, type, or name.
     *
     * @queryParam search string Filter upgrades by name. Example: Soulstone Cache
     * @queryParam domain string Filter by upgrade domain (character or crew). Example: character
     * @queryParam faction string Filter by faction enum value. Example: arcanists
     * @queryParam type string Filter by upgrade type. Example: limited
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $upgrades = Upgrade::query()
            ->when($request->query('domain'), fn ($q, $domain) => $q->where('domain', $domain))
            ->when($request->query('faction'), fn ($q, $faction) => $q->where('faction', $faction))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
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
