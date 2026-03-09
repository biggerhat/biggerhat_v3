<?php

namespace App\Http\Controllers\API;

use App\Enums\CharacterStationEnum;
use App\Enums\FactionEnum;
use App\Enums\SculptVersionEnum;
use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Marker;
use App\Models\Package;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Models\Token;
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
            'token' => $this->showToken($slug),
            'marker' => $this->showMarker($slug),
            'package' => $this->showPackage($slug),
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

        $factions = Character::whereHas('keywords', fn ($q) => $q->where('slug', $slug))
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

        $characterCount = Character::where('faction', $faction->value)->count();
        $masterCount = Character::where('faction', $faction->value)
            ->where('station', CharacterStationEnum::Master->value)->count();
        $keywordCount = Keyword::whereHas('characters', fn ($q) => $q->where('faction', $faction->value))->count();

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
            'triggers' => $action->triggers->map(fn ($t) => [
                'name' => $t->name,
                'suits' => $t->suits,
                'costs_stone' => $t->costs_stone,
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
        $package = Package::where('slug', $slug)->first();

        if (! $package) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json([
            'name' => $package->name,
            'type' => 'package',
            'slug' => $package->slug,
            'factions' => $package->factions,
            'link' => route('packages.view', $package->slug),
        ]);
    }
}
