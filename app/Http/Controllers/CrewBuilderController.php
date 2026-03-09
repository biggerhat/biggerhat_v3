<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Http\Resources\CharacterCrewBuilderResource;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Keyword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrewBuilderController extends Controller
{
    private function getCharacters()
    {
        return Character::with(['keywords', 'characteristics', 'crewUpgrades.keywords', 'totem', 'miniatures'])
            ->where('is_hidden', false)
            ->where(function ($query) {
                $query->where('station', 'master')
                    ->orWhere(function ($q) {
                        $q->where('is_unhirable', false)
                            ->whereNotNull('cost');
                    });
            })
            ->orderBy('station_sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->get();
    }

    private function getBaseProps(Request $request): array
    {
        $characters = $this->getCharacters();

        return [
            'factions' => fn () => FactionEnum::buildDetails(),
            'keywords' => fn () => Keyword::orderBy('name', 'ASC')->get(['id', 'name', 'slug']),
            'characters' => fn () => CharacterCrewBuilderResource::collection($characters)->toArray($request),
            'savedBuilds' => fn () => Auth::check()
                ? CrewBuild::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->get()
                : [],
        ];
    }

    public function index(Request $request)
    {
        return inertia('Tools/CrewBuilder/Index', $this->getBaseProps($request));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'faction' => 'required|string',
            'master_id' => 'required|exists:characters,id',
            'encounter_size' => 'required|integer|min:1',
            'crew_data' => 'required|array',
        ]);

        $build = CrewBuild::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'id' => $build->id,
            'share_code' => $build->share_code,
        ]);
    }

    public function update(Request $request, CrewBuild $crewBuild)
    {
        if (Auth::id() !== $crewBuild->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'faction' => 'sometimes|string',
            'master_id' => 'sometimes|exists:characters,id',
            'encounter_size' => 'sometimes|integer|min:1',
            'crew_data' => 'sometimes|array',
            'is_archived' => 'sometimes|boolean',
        ]);

        $crewBuild->update($validated);

        return response()->json([
            'id' => $crewBuild->id,
            'share_code' => $crewBuild->share_code,
            'is_archived' => $crewBuild->is_archived,
        ]);
    }

    public function destroy(CrewBuild $crewBuild)
    {
        if (Auth::id() !== $crewBuild->user_id) {
            abort(403);
        }

        $crewBuild->delete();

        return response()->json(['success' => true]);
    }

    public function share(Request $request, string $shareCode)
    {
        $build = CrewBuild::where('share_code', $shareCode)->firstOrFail();

        return inertia('Tools/CrewBuilder/Index', [
            ...$this->getBaseProps($request),
            'sharedBuild' => $build,
        ]);
    }
}
