<?php

namespace App\Http\Controllers\API;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Keyword;
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

        $characters = Character::where('display_name', 'LIKE', "%{$q}%")
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

        $keywords = Keyword::where('name', 'LIKE', "%{$q}%")
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

        $upgrades = Upgrade::where('name', 'LIKE', "%{$q}%")
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

        $actions = Action::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($actions as $action) {
            $results[] = [
                'entityType' => 'action',
                'entityId' => $action->id,
                'entitySlug' => $action->slug,
                'displayName' => $action->name,
            ];
        }

        $abilities = Ability::where('name', 'LIKE', "%{$q}%")
            ->limit(5)
            ->get(['id', 'name', 'slug']);
        foreach ($abilities as $ability) {
            $results[] = [
                'entityType' => 'ability',
                'entityId' => $ability->id,
                'entitySlug' => $ability->slug,
                'displayName' => $ability->name,
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
            default => response()->json(['error' => 'Unknown entity type'], 404),
        };
    }

    private function showCharacter(string $slug): JsonResponse
    {
        $character = Character::where('slug', $slug)->with('miniatures')->first();

        if (! $character) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $miniature = $character->miniatures->first();

        return response()->json([
            'name' => $character->display_name,
            'type' => 'character',
            'slug' => $character->slug,
            'miniatures' => $character->miniatures->map(fn ($m) => [
                'id' => $m->id,
                'display_name' => $m->display_name,
                'slug' => $m->slug,
                'front_image' => $m->front_image,
                'back_image' => $m->back_image,
            ]),
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
            'link' => route('upgrades.view', $upgrade->slug),
        ]);
    }

    private function showKeyword(string $slug): JsonResponse
    {
        $keyword = Keyword::where('slug', $slug)->first();

        if (! $keyword) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $keyword->name,
            'type' => 'keyword',
            'slug' => $keyword->slug,
            'link' => route('keywords.view', $keyword->slug),
        ]);
    }

    private function showFaction(string $slug): JsonResponse
    {
        $faction = FactionEnum::tryFrom($slug);

        if (! $faction) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $faction->label(),
            'type' => 'faction',
            'slug' => $faction->value,
            'link' => route('factions.view', $faction->value),
        ]);
    }

    private function showAction(string $slug): JsonResponse
    {
        $action = Action::where('slug', $slug)->withCount('characters')->first();

        if (! $action) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $action->name,
            'type' => 'action',
            'slug' => $action->slug,
            'action_type' => $action->type,
            'is_signature' => $action->is_signature,
            'costs_stone' => $action->costs_stone,
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
}
