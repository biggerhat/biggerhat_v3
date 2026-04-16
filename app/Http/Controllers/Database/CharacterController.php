<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class CharacterController extends Controller
{
    public function view(Request $request, Character $character, Miniature $miniature): Response|ResponseFactory
    {
        $character->loadMissing(
            'miniatures.podLinks', 'keywords', 'characteristics',
            'crewUpgrades', 'characterUpgrades', 'packages',
            'tokens', 'markers',
            'lores.media',
            'totem.standardMiniatures', 'isTotemFor.standardMiniatures',
            'summons.miniatures', 'summonedBy.miniatures',
            'replacesInto.miniatures', 'replacedBy.miniatures',
        );
        $character->load(['blueprints' => fn ($q) => $q->withImage()]);
        $character->load(['transmissions' => fn ($q) => $q->with('channel:id,name,slug,image')->latest('release_date')->limit(3)]);
        $character->load(['blogPosts' => fn ($q) => $q->published()->with('category:id,name')->latest('published_at')->limit(3)]);

        return inertia('Characters/View', [
            'character' => $character,
            'miniature' => $miniature,
            /** @phpstan-ignore return.type */
            'related_characters' => fn () => Character::standard()
                ->where('id', '!=', $character->id)
                ->where('is_hidden', false)
                ->whereHas('keywords', fn ($q) => $q->whereIn('keywords.id', $character->keywords->pluck('id')))
                ->with('miniatures')
                ->whereHas('miniatures')
                ->limit(12)
                ->get()
                ->map(fn ($c) => [
                    'display_name' => $c->display_name,
                    'slug' => $c->slug,
                    'faction' => $c->faction->value,
                    'miniature_id' => $c->miniatures->first()?->id,
                    'miniature_slug' => $c->miniatures->first()?->slug,
                ]),
        ]);
    }

    public function random(Request $request): \Illuminate\Http\RedirectResponse
    {
        $character = Character::standard()->with('miniatures')->whereHas('miniatures')->inRandomOrder()->first();

        if (! $character || $character->miniatures->isEmpty()) {
            return redirect()->route('home');
        }

        $miniature = $character->miniatures->first();

        return redirect()->route('characters.view', ['character' => $character->slug, 'miniature' => $miniature->id, 'slug' => $miniature->slug]);
    }
}
