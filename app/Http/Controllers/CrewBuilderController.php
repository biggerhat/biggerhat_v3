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

        return Character::with(['keywords', 'characteristics', 'crewUpgrades.keywords', 'totem', 'miniatures', 'actions.triggers'])
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

    public function editor(Request $request)
    {
        return inertia('Tools/CrewBuilder/Index', $this->getBaseProps($request));
    }

    public function browse(Request $request)
    {
        $query = CrewBuild::where('is_public', true)
            ->where('is_archived', false)
            ->with('user:id,name', 'master:id,name,title,display_name,slug')
            ->latest();

        if ($faction = $request->get('faction')) {
            $query->where('faction', $faction);
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $publicCrews = $query->paginate(12)->through(fn (CrewBuild $build) => [
            'id' => $build->id,
            'name' => $build->name,
            'faction' => $build->getRawOriginal('faction'),
            'faction_label' => $build->faction->label(),
            'faction_color' => $build->faction->color(),
            'faction_logo' => $build->faction->logo(),
            'master_name' => $build->master->display_name,
            'encounter_size' => $build->encounter_size,
            'share_code' => $build->share_code,
            'user_name' => $build->user?->name,
            'created_at' => $build->created_at->diffForHumans(),
        ]);

        $myCrews = Auth::check()
            ? CrewBuild::where('user_id', Auth::id())
                ->where('is_archived', false)
                ->with('master:id,name,title,display_name,slug')
                ->latest('updated_at')
                ->take(6)
                ->get()
                ->map(fn (CrewBuild $build) => [
                    'id' => $build->id,
                    'name' => $build->name,
                    'faction' => $build->getRawOriginal('faction'),
                    'faction_label' => $build->faction->label(),
                    'faction_color' => $build->faction->color(),
                    'faction_logo' => $build->faction->logo(),
                    'master_name' => $build->master->display_name,
                    'encounter_size' => $build->encounter_size,
                    'share_code' => $build->share_code,
                    'is_public' => $build->is_public,
                    'updated_at' => $build->updated_at->diffForHumans(),
                ])
            : [];

        return inertia('Tools/CrewBuilder/Browse', [
            'crews' => $publicCrews,
            'my_crews' => fn () => $myCrews,
            'factions' => fn () => FactionEnum::buildDetails(),
            'active_faction' => $request->get('faction'),
            'active_search' => $request->get('search'),
        ]);
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

    public function details(CrewBuild $crewBuild)
    {
        if (Auth::id() !== $crewBuild->user_id) {
            abort(403);
        }

        $master = Character::with(['keywords', 'crewUpgrades.keywords', 'totem'])
            ->find($crewBuild->master_id);

        if (! $master) {
            return response()->json(['members' => [], 'total_spent' => 0, 'soulstone_pool' => 0, 'ook_count' => 0]);
        }

        // Build leader keyword slugs
        $leaderKeywordSlugs = $master->keywords->pluck('slug')->toArray();
        foreach ($master->crewUpgrades as $upgrade) {
            foreach ($upgrade->keywords as $keyword) {
                $leaderKeywordSlugs[] = $keyword->slug;
            }
        }
        $leaderKeywordSlugs = array_unique($leaderKeywordSlugs);

        $members = [];

        // Add master
        for ($i = 0; $i < max(1, $master->count ?? 1); $i++) {
            $members[] = [
                'display_name' => $master->display_name,
                'cost' => 0,
                'effective_cost' => 0,
                'category' => 'leader',
                'faction' => $master->faction->value,
            ];
        }

        // Add totem
        if ($master->has_totem_id && $master->totem) {
            $totem = $master->totem;
            for ($i = 0; $i < max(1, $totem->count ?? 1); $i++) {
                $members[] = [
                    'display_name' => $totem->display_name,
                    'cost' => 0,
                    'effective_cost' => 0,
                    'category' => 'totem',
                    'faction' => $totem->faction->value,
                ];
            }
        }

        // Add crew members
        $crewCharacters = Character::with(['keywords', 'characteristics'])
            ->whereIn('id', $crewBuild->crew_data ?? [])
            ->get()
            ->keyBy('id');

        $totalSpent = 0;
        $ookCount = 0;

        foreach ($crewBuild->crew_data ?? [] as $charId) {
            $character = $crewCharacters->get($charId);
            if (! $character) {
                continue;
            }

            $sharesKeyword = $character->keywords->pluck('slug')->intersect($leaderKeywordSlugs)->isNotEmpty();
            $isVersatile = $character->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');

            if ($sharesKeyword) {
                $category = 'in-keyword';
            } elseif ($isVersatile) {
                $category = 'versatile';
            } else {
                $category = 'ook';
            }

            $effectiveCost = $category === 'ook' ? ($character->cost + 1) : $character->cost;
            $totalSpent += $effectiveCost;

            if ($category === 'ook') {
                $ookCount++;
            }

            $members[] = [
                'display_name' => $character->display_name,
                'cost' => $character->cost,
                'effective_cost' => $effectiveCost,
                'category' => $category,
                'faction' => $character->faction->value,
            ];
        }

        $remaining = $crewBuild->encounter_size - $totalSpent;
        $soulstonePool = $remaining > 6 ? 6 : max(0, $remaining);

        return response()->json([
            'members' => $members,
            'total_spent' => $totalSpent,
            'soulstone_pool' => $soulstonePool,
            'ook_count' => $ookCount,
        ]);
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
                'user_name' => $build->user?->name,
                'updated_at' => $build->updated_at->toISOString(),
            ],
        ]);
    }
}
