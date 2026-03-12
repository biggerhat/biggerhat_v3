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
    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, CrewBuild>  $builds
     * @return array<int, array<string, mixed>>
     */
    private function serializeBuilds($builds): array
    {
        $builds->load(['copiedFrom' => fn ($q) => $q->select('id', 'name', 'share_code', 'is_public', 'user_id')]);

        return $builds->map(fn (CrewBuild $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'description' => $b->description,
            'share_code' => $b->share_code,
            'faction' => $b->getRawOriginal('faction'),
            'master_id' => $b->master_id,
            'encounter_size' => $b->encounter_size,
            'crew_data' => $b->crew_data,
            'is_archived' => $b->is_archived,
            'is_public' => $b->is_public,
            'updated_at' => $b->updated_at->toISOString(),
            'copied_from' => $b->copied_from_id ? (static function (CrewBuild $b) {
                /** @var CrewBuild|null $source */
                $source = $b->copiedFrom;

                return $source ? [
                    'name' => $source->name,
                    'share_code' => $source->share_code,
                    'is_public' => $source->is_public,
                ] : null;
            })($b) : null,
        ])->all();
    }

    private function getCharacters()
    {
        // Collect IDs of all totems referenced by masters so they're always included
        $totemIds = Character::where('station', 'master')
            ->whereNotNull('has_totem_id')
            ->pluck('has_totem_id')
            ->unique();

        return Character::with(['keywords', 'characteristics', 'crewUpgrades.keywords', 'totem', 'miniatures'])
            ->where('is_hidden', false)
            ->where(function ($query) use ($totemIds) {
                $query->where('station', 'master')
                    ->orWhere(function ($q) {
                        $q->where('is_unhirable', false)
                            ->whereNotNull('cost');
                    })
                    ->orWhereIn('id', $totemIds);
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
                ? $this->serializeBuilds(CrewBuild::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->get())
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
            'description' => 'nullable|array',
            'faction' => 'required|string',
            'master_id' => 'required|exists:characters,id',
            'encounter_size' => 'required|integer|min:1',
            'crew_data' => 'present|array',
            'copied_from_id' => 'nullable|exists:crew_builds,id',
        ]);

        $build = CrewBuild::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'id' => $build->id,
            'share_code' => $build->share_code,
            'is_public' => $build->is_public,
        ]);
    }

    public function update(Request $request, CrewBuild $crewBuild)
    {
        if (Auth::id() !== $crewBuild->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|array',
            'faction' => 'sometimes|string',
            'master_id' => 'sometimes|exists:characters,id',
            'encounter_size' => 'sometimes|integer|min:1',
            'crew_data' => 'sometimes|array',
            'is_archived' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
        ]);

        $crewBuild->update($validated);

        return response()->json([
            'id' => $crewBuild->id,
            'share_code' => $crewBuild->share_code,
            'is_archived' => $crewBuild->is_archived,
            'is_public' => $crewBuild->is_public,
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
        /** @var CrewBuild $build */
        $build = CrewBuild::where('share_code', $shareCode)->firstOrFail();

        // Private builds are only viewable by their owner
        if (! $build->is_public && Auth::id() !== $build->user_id) {
            return inertia('Tools/CrewBuilder/Private');
        }

        $characters = $this->getCharacters();

        return inertia('Tools/CrewBuilder/View', [
            'factions' => fn () => FactionEnum::buildDetails(),
            'characters' => fn () => CharacterCrewBuilderResource::collection($characters)->toArray($request),
            'build' => [
                'id' => $build->id,
                'name' => $build->name,
                'description' => $build->description,
                'share_code' => $build->share_code,
                'faction' => $build->getRawOriginal('faction'),
                'master_id' => $build->master_id,
                'encounter_size' => $build->encounter_size,
                'crew_data' => $build->crew_data,
                'is_public' => $build->is_public,
                'user_id' => $build->user_id,
                'user_name' => $build->user()->value('name'),
                'updated_at' => $build->updated_at->toISOString(),
            ],
        ]);
    }
}
