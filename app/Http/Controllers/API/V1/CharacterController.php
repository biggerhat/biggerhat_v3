<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\CharacterResource;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * @tags Characters
 */
class CharacterController extends Controller
{
    /**
     * List all characters
     *
     * Returns a paginated list of visible characters with their miniatures, keywords, characteristics, and totem.
     *
     * @queryParam search string Filter characters by name, display name, or nicknames. Example: Rasputina
     * @queryParam faction string Filter by faction enum value. Example: arcanists
     * @queryParam station string Filter by station enum value. Example: master
     * @queryParam per_page int Number of results per page (max 100). Example: 15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->query('search');

        $query = Character::query()
            ->where('is_hidden', false)
            ->with(['miniatures', 'keywords', 'characteristics', 'totem'])
            ->when($request->query('faction'), fn ($q, $faction) => $q->where('faction', $faction))
            ->when($request->query('station'), fn ($q, $station) => $q->where('station', $station))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%")
                    ->orWhere('nicknames', 'LIKE', "%{$search}%");
            }));

        // When searching, prioritize exact name/display_name matches, then prefix matches, then substring.
        if ($search) {
            $query->orderBy(DB::raw('CASE
                WHEN LOWER(name) = LOWER(?) THEN 0
                WHEN LOWER(display_name) = LOWER(?) THEN 0
                WHEN LOWER(name) LIKE LOWER(?) THEN 1
                WHEN LOWER(display_name) LIKE LOWER(?) THEN 1
                ELSE 2
            END'))->addBinding([$search, $search, $search.'%', $search.'%'], 'order');
        }

        $characters = $query
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return CharacterResource::collection($characters);
    }

    /**
     * Get a single character
     *
     * Returns a character with all related data including miniatures, keywords, characteristics, totem, actions, triggers, abilities, markers, tokens, and character upgrades.
     */
    public function show(Character $character): CharacterResource
    {
        abort_if($character->is_hidden, 404);

        $character->loadMissing(['miniatures', 'keywords', 'characteristics', 'totem', 'actions.triggers', 'abilities', 'markers', 'tokens', 'characterUpgrades']);

        return new CharacterResource($character);
    }
}
