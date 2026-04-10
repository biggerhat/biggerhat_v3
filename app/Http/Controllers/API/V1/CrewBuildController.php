<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CrewBuild;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Crew Builds
 */
class CrewBuildController extends Controller
{
    /**
     * List public crew builds
     *
     * @queryParam search string Filter by name. Example: Rasputina
     * @queryParam faction string Filter by faction. Example: arcanists
     * @queryParam per_page int Results per page (max 50). Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = CrewBuild::where('is_public', true)
            ->with('user:id,name', 'master:id,display_name,faction');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%'.$request->get('search').'%');
        }

        if ($request->filled('faction')) {
            $query->where('faction', $request->get('faction'));
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $builds = $query->latest('updated_at')->paginate($perPage);

        return response()->json($builds->through(fn (CrewBuild $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'share_code' => $b->share_code,
            'faction' => $b->getRawOriginal('faction'),
            'master' => $b->master?->display_name, // @phpstan-ignore-line
            'encounter_size' => $b->encounter_size,
            'model_count' => count($b->crew_data ?? []),
            'user' => $b->user?->name,
            'updated_at' => $b->updated_at?->toISOString(),
            'url' => route('tools.crew_builder.share', $b->share_code),
        ]));
    }

    /**
     * View a public crew build by share code
     */
    public function show(string $shareCode): JsonResponse
    {
        $crew = CrewBuild::where('share_code', $shareCode)
            ->where('is_public', true)
            ->with('user:id,name', 'master.keywords', 'master.miniatures', 'master.crewUpgrades')
            ->firstOrFail();

        $master = $crew->master;
        $crewCharacterIds = $crew->crew_data ?? [];
        $crewCharacters = Character::with('miniatures')
            ->whereIn('id', $crewCharacterIds)
            ->get()
            ->keyBy('id');

        $members = [];
        foreach ($crewCharacterIds as $charId) {
            $character = $crewCharacters->get($charId);
            if ($character) {
                $members[] = [
                    'display_name' => $character->display_name,
                    'faction' => $character->getRawOriginal('faction'),
                    'cost' => $character->cost,
                    'station' => $character->station?->value,
                ];
            }
        }

        return response()->json([
            'name' => $crew->name,
            'share_code' => $crew->share_code,
            'faction' => $crew->getRawOriginal('faction'),
            'master' => $master->display_name,
            'encounter_size' => $crew->encounter_size,
            'members' => $members,
            'crew_upgrades' => $master->crewUpgrades->map(fn ($u) => [
                'name' => $u->name,
                'is_active' => $u->id === $crew->crew_upgrade_id,
            ])->values()->all() ?? [],
            'user' => $crew->user?->name,
            'url' => route('tools.crew_builder.share', $crew->share_code),
        ]);
    }
}
