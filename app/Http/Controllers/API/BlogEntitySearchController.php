<?php

namespace App\Http\Controllers\API;

use App\Enums\CharacterStationEnum;
use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\CrewBuild;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Package;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
use App\Models\Trigger;
use App\Models\Upgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogEntitySearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        $characters = Character::standard()->where('display_name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'display_name as name', 'slug', 'faction']);
        foreach ($characters as $char) {
            $results[] = [
                'entityType' => 'character',
                'entityId' => $char->id,
                'entitySlug' => $char->slug,
                'displayName' => $char->name,
            ];
        }

        $keywords = Keyword::standard()->where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($keywords as $kw) {
            $results[] = [
                'entityType' => 'keyword',
                'entityId' => $kw->id,
                'entitySlug' => $kw->slug,
                'displayName' => $kw->name,
            ];
        }

        $factions = collect(FactionEnum::cases())
            ->filter(fn (FactionEnum $f) => str_contains(strtolower($f->label()), strtolower($q)))
            ->take(5);
        foreach ($factions as $faction) {
            $results[] = [
                'entityType' => 'faction',
                'entityId' => $faction->value,
                'entitySlug' => $faction->value,
                'displayName' => $faction->label(),
            ];
        }

        $upgrades = Upgrade::standard()->where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($upgrades as $upgrade) {
            $results[] = [
                'entityType' => 'upgrade',
                'entityId' => $upgrade->id,
                'entitySlug' => $upgrade->slug,
                'displayName' => $upgrade->name,
            ];
        }

        $actions = Action::standard()->where('name', 'LIKE', "%{$q}%")
            ->withCount('characters')
            ->with(['characters' => fn ($q) => $q->select('display_name')->limit(1)])
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($actions as $action) {
            $firstChar = $action->characters->first()?->display_name;
            $extraCount = max(0, $action->characters_count - 1);
            $label = $action->name;
            if ($firstChar) {
                $label .= $extraCount > 0 ? " ({$firstChar} +{$extraCount})" : " ({$firstChar})";
            }
            $results[] = [
                'entityType' => 'action',
                'entityId' => $action->id,
                'entitySlug' => $action->slug,
                'displayName' => $label,
            ];
        }

        $abilities = Ability::standard()->where('name', 'LIKE', "%{$q}%")
            ->withCount('characters')
            ->with(['characters' => fn ($q) => $q->select('display_name')->limit(1)])
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($abilities as $ability) {
            $firstChar = $ability->characters->first()?->display_name;
            $extraCount = max(0, $ability->characters_count - 1);
            $label = $ability->name;
            if ($firstChar) {
                $label .= $extraCount > 0 ? " ({$firstChar} +{$extraCount})" : " ({$firstChar})";
            }
            $results[] = [
                'entityType' => 'ability',
                'entityId' => $ability->id,
                'entitySlug' => $ability->slug,
                'displayName' => $label,
            ];
        }

        $schemes = Scheme::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($schemes as $scheme) {
            $results[] = [
                'entityType' => 'scheme',
                'entityId' => $scheme->id,
                'entitySlug' => $scheme->slug,
                'displayName' => $scheme->name,
            ];
        }

        $strategies = Strategy::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($strategies as $strategy) {
            $results[] = [
                'entityType' => 'strategy',
                'entityId' => $strategy->id,
                'entitySlug' => $strategy->slug,
                'displayName' => $strategy->name,
            ];
        }

        $tokens = Token::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($tokens as $token) {
            $results[] = [
                'entityType' => 'token',
                'entityId' => $token->id,
                'entitySlug' => $token->slug,
                'displayName' => $token->name,
            ];
        }

        $markers = Marker::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($markers as $marker) {
            $results[] = [
                'entityType' => 'marker',
                'entityId' => $marker->id,
                'entitySlug' => $marker->slug,
                'displayName' => $marker->name,
            ];
        }

        $packages = Package::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($packages as $package) {
            $results[] = [
                'entityType' => 'package',
                'entityId' => $package->id,
                'entitySlug' => $package->slug,
                'displayName' => $package->name,
            ];
        }

        $deployments = collect(\App\Enums\DeploymentEnum::cases())
            ->filter(fn (\App\Enums\DeploymentEnum $d) => str_contains(strtolower($d->label()), strtolower($q)))
            ->take(5);
        foreach ($deployments as $deployment) {
            $results[] = [
                'entityType' => 'deployment',
                'entityId' => $deployment->value,
                'entitySlug' => $deployment->value,
                'displayName' => $deployment->label(),
            ];
        }

        $triggers = Trigger::where('name', 'LIKE', "%{$q}%")
            ->withCount('actions')
            ->with(['actions' => fn ($q) => $q->select('name')->limit(1)])
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($triggers as $trigger) {
            $firstAction = $trigger->actions->first()?->name;
            $extraCount = max(0, $trigger->actions_count - 1);
            $label = $trigger->name;
            if ($firstAction) {
                $label .= $extraCount > 0 ? " ({$firstAction} +{$extraCount})" : " ({$firstAction})";
            }
            $results[] = [
                'entityType' => 'trigger',
                'entityId' => $trigger->id,
                'entitySlug' => $trigger->slug,
                'displayName' => $label,
            ];
        }

        $crews = CrewBuild::where('is_public', true)
            ->where('name', 'LIKE', "%{$q}%")
            ->with('master:id,display_name', 'user:id,name')
            ->limit(5)
            ->get();
        foreach ($crews as $crew) {
            $results[] = [
                'entityType' => 'crew',
                'entityId' => $crew->id,
                'entitySlug' => $crew->share_code,
                'displayName' => $crew->name,
            ];
        }

        return response()->json(['results' => $results]);
    }

    public function show(string $type, string $slug): JsonResponse
    {
        return match ($type) {
            'character' => $this->showCharacter($slug),
            'upgrade' => $this->showUpgrade($slug),
            'keyword' => $this->showKeyword($slug),
            'faction' => $this->showFaction($slug),
            'action' => $this->showAction($slug),
            'ability' => $this->showAbility($slug),
            'scheme' => $this->showScheme($slug),
            'strategy' => $this->showStrategy($slug),
            'deployment' => $this->showDeployment($slug),
            'token' => $this->showToken($slug),
            'marker' => $this->showMarker($slug),
            'package' => $this->showPackage($slug),
            'trigger' => $this->showTrigger($slug),
            'crew' => $this->showCrew($slug),
            default => response()->json(['error' => 'Unknown entity type'], 404),
        };
    }

    private function showCharacter(string $slug): JsonResponse
    {
        $character = Character::where('slug', $slug)->with('miniatures')->first();

        if (! $character) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $miniature = $character->miniatures
            ->whereNotIn('version', SculptVersionEnum::promotionalEditions())
            ->sortByDesc(fn ($m) => $m->version === SculptVersionEnum::FourthEdition->value ? 1 : 0)
            ->first()
            ?? $character->miniatures->first();

        return response()->json([
            'name' => $character->display_name,
            'type' => 'character',
            'slug' => $character->slug,
            'miniature' => $miniature ? [
                'id' => $miniature->id,
                'display_name' => $miniature->display_name,
                'slug' => $miniature->slug,
                'front_image' => $miniature->front_image,
                'back_image' => $miniature->back_image,
            ] : null,
            'link' => $miniature ? route('characters.view', [
                'character' => $character->slug,
                'miniature' => $miniature->id,
                'slug' => $miniature->slug,
            ]) : null,
        ]);
    }

    private function showUpgrade(string $slug): JsonResponse
    {
        $upgrade = Upgrade::where('slug', $slug)->first();

        if (! $upgrade) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $upgrade->name,
            'type' => 'upgrade',
            'slug' => $upgrade->slug,
            'front_image' => $upgrade->front_image,
            'back_image' => $upgrade->back_image,
            'link' => route('upgrades.view', $upgrade->slug),
        ]);
    }

    private function showKeyword(string $slug): JsonResponse
    {
        $keyword = Keyword::where('slug', $slug)->withCount('characters', 'masters')->first();

        if (! $keyword) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $factions = Character::standard()->whereHas('keywords', fn ($q) => $q->where('slug', $slug))
            ->distinct('faction')
            ->pluck('faction')
            ->map(fn (string $f) => FactionEnum::tryFrom($f))
            ->filter()
            ->map(fn (FactionEnum $f) => [
                'name' => $f->label(),
                'slug' => $f->value,
                'color' => $f->color(),
                'logo' => config('app.url').$f->logo(),
            ])
            ->values();

        return response()->json([
            'name' => $keyword->name,
            'type' => 'keyword',
            'slug' => $keyword->slug,
            'link' => route('keywords.view', $keyword->slug),
            'characters_count' => $keyword->characters_count,
            'masters_count' => $keyword->masters_count,
            'factions' => $factions,
        ]);
    }

    private function showFaction(string $slug): JsonResponse
    {
        $faction = FactionEnum::tryFrom($slug);

        if (! $faction) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $characterCount = Character::standard()->where('faction', $faction->value)->count();
        $masterCount = Character::standard()->where('faction', $faction->value)
            ->where('station', CharacterStationEnum::Master->value)->count();
        $keywordCount = Keyword::standard()->whereHas('characters', fn ($q) => $q->where('faction', $faction->value))->count();

        return response()->json([
            'name' => $faction->label(),
            'type' => 'faction',
            'slug' => $faction->value,
            'color' => $faction->color(),
            'logo' => config('app.url').$faction->logo(),
            'link' => route('factions.view', $faction->value),
            'characters_count' => $characterCount,
            'masters_count' => $masterCount,
            'keywords_count' => $keywordCount,
        ]);
    }

    private function showAction(string $slug): JsonResponse
    {
        $action = Action::where('slug', $slug)->with('triggers')->withCount('characters')->first();

        if (! $action) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $action->name,
            'type' => 'action',
            'slug' => $action->slug,
            'action_type' => $action->type,
            'is_signature' => $action->is_signature,
            'stone_cost' => $action->stone_cost,
            'range' => $action->range,
            'range_type' => $action->range_type,
            'stat' => $action->stat,
            'stat_suits' => $action->stat_suits,
            'resisted_by' => $action->resisted_by,
            'target_number' => $action->target_number,
            'target_suits' => $action->target_suits,
            'damage' => $action->damage,
            'description' => $action->description,
            'characters_count' => $action->characters_count,
            'triggers' => $action->triggers->map(fn ($t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'stone_cost' => $t->stone_cost,
                'description' => $t->description,
            ]),
            'link' => route('actions.index', ['name' => $action->name]),
        ]);
    }

    private function showAbility(string $slug): JsonResponse
    {
        $ability = Ability::where('slug', $slug)->withCount('characters')->first();

        if (! $ability) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $ability->name,
            'type' => 'ability',
            'slug' => $ability->slug,
            'suits' => $ability->suits,
            'defensive_ability_type' => $ability->defensive_ability_type,
            'costs_stone' => $ability->costs_stone,
            'description' => $ability->description,
            'characters_count' => $ability->characters_count,
            'link' => route('abilities.index', ['name' => $ability->name]),
        ]);
    }

    private function showScheme(string $slug): JsonResponse
    {
        $scheme = Scheme::where('slug', $slug)->first();

        if (! $scheme) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $scheme->name,
            'type' => 'scheme',
            'slug' => $scheme->slug,
            'season' => $scheme->season->label(),
            'image' => $scheme->image_url,
            'link' => route('schemes.view', $scheme->slug),
        ]);
    }

    private function showStrategy(string $slug): JsonResponse
    {
        $strategy = Strategy::where('slug', $slug)->first();

        if (! $strategy) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $strategy->name,
            'type' => 'strategy',
            'slug' => $strategy->slug,
            'season' => $strategy->season->label(),
            'suit' => $strategy->suit?->label(),
            'image' => $strategy->image_url,
            'link' => route('strategies.view', $strategy->slug),
        ]);
    }

    private function showDeployment(string $slug): JsonResponse
    {
        $deployment = DeploymentEnum::tryFrom($slug);

        if (! $deployment) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $deployment->label(),
            'type' => 'deployment',
            'slug' => $deployment->value,
            'description' => $deployment->description(),
            'image' => $deployment->imageUrl(),
        ]);
    }

    private function showToken(string $slug): JsonResponse
    {
        $token = Token::where('slug', $slug)->first();

        if (! $token) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $token->name,
            'type' => 'token',
            'slug' => $token->slug,
            'description' => $token->description,
            'link' => route('tokens.index', ['name' => $token->name]),
        ]);
    }

    private function showMarker(string $slug): JsonResponse
    {
        $marker = Marker::where('slug', $slug)->first();

        if (! $marker) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $marker->name,
            'type' => 'marker',
            'slug' => $marker->slug,
            'description' => $marker->description,
            'base' => $marker->base,
            'link' => route('markers.index', ['name' => $marker->name]),
        ]);
    }

    private function showPackage(string $slug): JsonResponse
    {
        $package = Package::where('slug', $slug)->withCount('characters', 'miniatures')->first();

        if (! $package) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $package->name,
            'type' => 'package',
            'slug' => $package->slug,
            'front_image' => $package->front_image,
            'factions' => $package->factions,
            'characters_count' => $package->characters_count,
            'miniatures_count' => $package->miniatures_count,
            'link' => route('packages.view', $package->slug),
        ]);
    }

    private function showTrigger(string $slug): JsonResponse
    {
        $trigger = Trigger::where('slug', $slug)->withCount('actions')->first();

        if (! $trigger) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $trigger->name,
            'type' => 'trigger',
            'slug' => $trigger->slug,
            'suits' => $trigger->suits,
            'stone_cost' => $trigger->stone_cost,
            'description' => $trigger->description,
            'actions_count' => $trigger->actions_count,
            'link' => route('triggers.index', ['name' => $trigger->name]),
        ]);
    }

    private function showCrew(string $shareCode): JsonResponse
    {
        $crew = CrewBuild::where('share_code', $shareCode)
            ->where('is_public', true)
            ->with('master.keywords', 'master.miniatures', 'master.crewUpgrades', 'user:id,name')
            ->first();

        if (! $crew) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $master = $crew->master;
        $leaderKeywordSlugs = $master->keywords->pluck('slug')->toArray();

        // Crew upgrade info
        $activeUpgradeId = $crew->crew_upgrade_id;
        $crewUpgrades = $master->crewUpgrades->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'front_image' => $u->front_image,
            'back_image' => $u->back_image,
            'is_active' => $u->id === $activeUpgradeId,
        ]);

        // Build members
        $crewCharacterIds = $crew->crew_data ?? [];
        $crewCharacters = Character::with('keywords', 'characteristics', 'miniatures')
            ->whereIn('id', $crewCharacterIds)
            ->get()
            ->keyBy('id');

        $masterMini = $master->miniatures->first();
        $members = [];

        // Leader
        $members[] = [
            'display_name' => $master->display_name,
            'faction' => $master->getRawOriginal('faction'),
            'cost' => 0,
            'category' => 'leader',
            'front_image' => $masterMini?->front_image,
        ];

        // Totem(s) — respect count for multi-copy totems
        if ($master->has_totem_id) {
            $totem = $crewCharacters->get($master->has_totem_id) ?? Character::with('miniatures')->find($master->has_totem_id);
            if ($totem) {
                $totemCount = max(1, $totem->count ?? 1);
                for ($i = 0; $i < $totemCount; $i++) {
                    $members[] = [
                        'display_name' => $totem->display_name,
                        'faction' => $totem->getRawOriginal('faction'),
                        'cost' => 0,
                        'category' => 'totem',
                        'front_image' => $totem->miniatures->first()?->front_image,
                    ];
                }
            }
        }

        foreach ($crewCharacterIds as $charId) {
            $character = $crewCharacters->get($charId);
            if (! $character) {
                continue;
            }

            $sharesKeyword = $character->keywords->pluck('slug')->intersect($leaderKeywordSlugs)->isNotEmpty();
            $isVersatile = $character->characteristics->pluck('name')->map(fn ($n) => strtolower($n))->contains('versatile');
            $category = $sharesKeyword ? 'in-keyword' : ($isVersatile ? 'versatile' : 'ook');
            $effectiveCost = $category === 'ook' ? ($character->cost + 1) : $character->cost;

            $members[] = [
                'display_name' => $character->display_name,
                'faction' => $character->getRawOriginal('faction'),
                'cost' => $effectiveCost,
                'category' => $category,
                'front_image' => $character->miniatures->first()?->front_image,
            ];
        }

        $totalSpent = collect($members)->sum('cost');
        $remaining = $crew->encounter_size - $totalSpent;
        $soulstonePool = $remaining > 6 ? 6 : max(0, $remaining);

        return response()->json([
            'name' => $crew->name,
            'type' => 'crew',
            'slug' => $crew->share_code,
            'faction' => $crew->getRawOriginal('faction'),
            'faction_label' => $crew->faction->label(),
            'faction_logo' => $crew->faction->logo(),
            'master_name' => $master->display_name,
            'encounter_size' => $crew->encounter_size,
            'total_spent' => $totalSpent,
            'soulstone_pool' => $soulstonePool,
            'member_count' => count($members),
            'members' => $members,
            'crew_upgrades' => $crewUpgrades,
            'user_name' => $crew->user?->name,
            'link' => route('tools.crew_builder.share', $crew->share_code),
        ]);
    }
}
