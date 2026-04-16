<?php

namespace App\Http\Controllers;

use App\Enums\FactionEnum;
use App\Http\Resources\CharacterCrewBuilderResource;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\CustomCharacter;
use App\Models\Keyword;
use App\Models\Upgrade;
use Illuminate\Http\JsonResponse;
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
            'custom_crew_data' => $b->custom_crew_data,
            'miniature_selections' => $b->miniature_selections,
            'crew_upgrade_id' => $b->crew_upgrade_id,
            'is_archived' => $b->is_archived,
            'is_public' => $b->is_public,
            'custom_references' => $b->custom_references,
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
        $totemIds = Character::standard()->where('station', 'master')
            ->whereNotNull('has_totem_id')
            ->pluck('has_totem_id')
            ->unique();

        return Character::standard()->with(['keywords', 'characteristics', 'crewUpgrades.keywords', 'totem', 'miniatures', 'actions.triggers'])
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
            'keywords' => fn () => Keyword::standard()->orderBy('name', 'ASC')->get(['id', 'name', 'slug']),
            'characters' => fn () => CharacterCrewBuilderResource::collection($characters)->toArray($request),
            'savedBuilds' => fn () => Auth::check()
                ? $this->serializeBuilds(CrewBuild::where('user_id', Auth::id())->orderBy('updated_at', 'desc')->get())
                : [],
            'customCharacters' => fn () => Auth::check()
                ? CustomCharacter::where('user_id', Auth::id())
                    ->orderBy('name')
                    ->get()
                    ->map(fn (CustomCharacter $c) => [
                        'id' => $c->id,
                        'display_name' => $c->display_name,
                        'name' => $c->name,
                        'title' => $c->title,
                        'slug' => $c->slug,
                        'faction' => $c->getRawOriginal('faction'),
                        'station' => $c->station->value,
                        'cost' => $c->cost,
                        'health' => $c->health,
                        'speed' => $c->speed,
                        'defense' => $c->defense,
                        'willpower' => $c->willpower,
                        'count' => $c->count ?? 1,
                        'keywords' => $c->keywords ?? [],
                        'characteristics' => $c->characteristics ?? [],
                        'front_image' => null,
                        'back_image' => null,
                        'is_custom' => true,
                    ])->toArray()
                : [],
            'ownedCharacterIds' => fn () => Auth::check()
                ? Auth::user()->collectionMiniatures()
                    ->pluck('character_id')
                    ->unique()
                    ->values()
                    ->all()
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
            'custom_crew_data' => 'nullable|array',
            'miniature_selections' => 'nullable|array',
            'crew_upgrade_id' => 'nullable|exists:upgrades,id',
            'copied_from_id' => 'nullable|exists:crew_builds,id',
            'custom_references' => 'nullable|array',
        ]);

        $build = CrewBuild::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        $build->refreshReferences();

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
            'custom_crew_data' => 'nullable|array',
            'miniature_selections' => 'nullable|array',
            'crew_upgrade_id' => 'nullable|exists:upgrades,id',
            'is_archived' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
            'custom_references' => 'nullable|array',
        ]);

        $crewBuild->update($validated);

        // Refresh references if crew composition or custom references changed
        if ($crewBuild->wasChanged(['crew_data', 'master_id', 'custom_references'])) {
            $crewBuild->refreshReferences();
        }

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

        // Resolve active crew upgrade and its hiring rules
        $activeUpgrade = $crewBuild->crew_upgrade_id ? Upgrade::find($crewBuild->crew_upgrade_id) : null;
        $hiringRules = $activeUpgrade?->hiring_rules;

        // Fixed crew mode (e.g. On Tour)
        if ($hiringRules && isset($hiringRules['fixed_crew_keyword'])) {
            $keyword = Keyword::where('slug', $hiringRules['fixed_crew_keyword'])->first();
            $fixedMembers = $keyword
                ? Character::standard()->whereHas('keywords', fn ($q) => $q->where('keywords.id', $keyword->id))
                    ->where('is_hidden', false)
                    ->get()
                : collect();

            $members = [];
            foreach ($fixedMembers as $char) {
                $isLeader = isset($hiringRules['alternate_leader_id']) && $char->id === $hiringRules['alternate_leader_id'];
                $members[] = [
                    'display_name' => $char->display_name,
                    'cost' => $isLeader ? 0 : $char->cost,
                    'effective_cost' => $isLeader ? 0 : $char->cost,
                    'category' => $isLeader ? 'leader' : 'fixed-crew',
                    'faction' => $char->faction->value,
                ];
            }

            return response()->json([
                'members' => $members,
                'total_spent' => 0,
                'soulstone_pool' => $hiringRules['fixed_cache'] ?? 6,
                'ook_count' => 0,
            ]);
        }

        // Build leader keyword slugs
        $leaderKeywordSlugs = $master->keywords->pluck('slug')->toArray();
        foreach ($master->crewUpgrades as $upgrade) {
            foreach ($upgrade->keywords as $keyword) {
                $leaderKeywordSlugs[] = $keyword->slug;
            }
        }
        $leaderKeywordSlugs = array_unique($leaderKeywordSlugs);

        // Required hires mode (e.g. Riders of Fate)
        $requiredCharIds = [];
        if ($hiringRules && isset($hiringRules['required_characteristic'])) {
            $requiredCharIds = Character::standard()->whereHas('characteristics', fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($hiringRules['required_characteristic'])]))
                ->where('is_hidden', false)
                ->pluck('id')
                ->toArray();
        }

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

            // Required hires get base cost, no OOK penalty
            if (in_array($character->id, $requiredCharIds)) {
                $totalSpent += $character->cost;
                $members[] = [
                    'display_name' => $character->display_name,
                    'cost' => $character->cost,
                    'effective_cost' => $character->cost,
                    'category' => 'required',
                    'faction' => $character->faction->value,
                ];

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

        // Add custom crew members
        foreach ($crewBuild->custom_crew_data ?? [] as $customEntry) {
            $customKeywords = collect($customEntry['keywords'] ?? [])
                ->pluck('name')
                ->map(fn ($n) => \Illuminate\Support\Str::slug($n))
                ->toArray();

            $sharesKeyword = ! empty(array_intersect($customKeywords, $leaderKeywordSlugs));
            $isVersatile = in_array('versatile', array_map('strtolower', $customEntry['characteristics'] ?? []));

            if ($sharesKeyword) {
                $category = 'in-keyword';
            } elseif ($isVersatile) {
                $category = 'versatile';
            } else {
                $category = 'ook';
            }

            $baseCost = $customEntry['cost'] ?? 0;
            $effectiveCost = $category === 'ook' ? ($baseCost + 1) : $baseCost;
            $totalSpent += $effectiveCost;

            if ($category === 'ook') {
                $ookCount++;
            }

            $members[] = [
                'display_name' => $customEntry['display_name'] ?? 'Custom',
                'cost' => $baseCost,
                'effective_cost' => $effectiveCost,
                'category' => $category,
                'faction' => $customEntry['faction'] ?? $crewBuild->getRawOriginal('faction'),
                'is_custom' => true,
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

    public function references(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ])['ids'];

        return response()->json(CrewBuild::computeReferences($ids));
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
                'custom_crew_data' => $build->custom_crew_data,
                'miniature_selections' => $build->miniature_selections,
                'crew_upgrade_id' => $build->crew_upgrade_id,
                'is_public' => $build->is_public,
                'user_id' => $build->user_id,
                'user_name' => $build->user?->name,
                'updated_at' => $build->updated_at->toISOString(),
            ],
        ]);
    }

    public function quickRef(string $shareCode)
    {
        $build = CrewBuild::where('share_code', $shareCode)->firstOrFail();

        if (! $build->is_public && Auth::id() !== $build->user_id) {
            abort(404);
        }

        $master = Character::with(['keywords', 'characteristics', 'actions.triggers', 'abilities', 'crewUpgrades', 'miniatures'])
            ->find($build->master_id);

        if (! $master) {
            abort(404);
        }

        $crewCharacterIds = $build->crew_data ?? [];
        $crewCharacters = Character::with(['keywords', 'characteristics', 'actions.triggers', 'abilities'])
            ->whereIn('id', $crewCharacterIds)
            ->get()
            ->keyBy('id');

        $leaderKeywords = $master->keywords->pluck('name')->toArray();

        $members = [];

        // Leader
        $members[] = $this->formatMemberForRef($master, 'leader');

        // Totem
        if ($master->has_totem_id) {
            $totem = $crewCharacters->get($master->has_totem_id)
                ?? Character::with(['keywords', 'characteristics', 'actions.triggers', 'abilities'])->find($master->has_totem_id);
            if ($totem) {
                $members[] = $this->formatMemberForRef($totem, 'totem');
            }
        }

        // Crew
        foreach ($crewCharacterIds as $charId) {
            $character = $crewCharacters->get($charId);
            if ($character && $character->id !== $master->has_totem_id) {
                $sharesKeyword = $character->keywords->pluck('slug')->intersect($master->keywords->pluck('slug'))->isNotEmpty();
                $isVersatile = $character->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
                $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
                $members[] = $this->formatMemberForRef($character, $category);
            }
        }

        $totalCost = collect($members)->sum('cost');
        $remaining = $build->encounter_size - $totalCost;
        $pool = min(6, max(0, $remaining));

        return inertia('Tools/CrewBuilder/QuickRef', [
            'crew' => [
                'name' => $build->name,
                'faction' => $build->getRawOriginal('faction'),
                'encounter_size' => $build->encounter_size,
                'soulstone_pool' => $pool,
                'master' => $master->display_name,
            ],
            'members' => $members,
        ]);
    }

    private function formatMemberForRef(Character $c, string $category): array
    {
        return [
            'display_name' => $c->display_name,
            'cost' => $category === 'leader' || $category === 'totem' ? 0 : ($category === 'ook' ? ($c->cost + 1) : $c->cost),
            'health' => $c->health,
            'defense' => $c->defense,
            'defense_suit' => $c->getRawOriginal('defense_suit'),
            'willpower' => $c->willpower,
            'willpower_suit' => $c->getRawOriginal('willpower_suit'),
            'speed' => $c->speed,
            'size' => $c->size,
            'station' => $c->station?->value,
            'category' => $category,
            'keywords' => $c->keywords->pluck('name')->toArray(),
            'characteristics' => $c->characteristics->pluck('name')->toArray(),
            'abilities' => $c->abilities->map(fn ($a) => [
                'name' => $a->name,
                'suits' => $a->suits,
                'description' => $a->description,
                'defensive_ability_type' => $a->getRawOriginal('defensive_ability_type'),
                'costs_stone' => (bool) $a->costs_stone,
            ])->toArray(),
            'actions' => $c->actions->map(fn ($a) => [
                'name' => $a->name,
                'type' => $a->getRawOriginal('type'),
                'stat' => $a->stat,
                'stat_suits' => $a->stat_suits,
                'range' => $a->range,
                'damage' => $a->damage,
                'description' => $a->description,
                'is_signature' => (bool) $a->is_signature,
                'stone_cost' => $a->stone_cost,
                'triggers' => $a->triggers->map(fn ($t) => [
                    'name' => $t->name,
                    'suits' => $t->suits,
                    'stone_cost' => $t->stone_cost,
                    'description' => $t->description,
                ])->toArray(),
            ])->toArray(),
        ];
    }
}
