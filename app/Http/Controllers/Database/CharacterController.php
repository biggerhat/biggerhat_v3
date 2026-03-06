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
            'miniatures', 'keywords', 'characteristics',
            'crewUpgrades', 'characterUpgrades',
            'totem.standardMiniatures', 'isTotemFor.standardMiniatures'
        );

        $relatedCharacters = Character::query()
            ->where('id', '!=', $character->id)
            ->where('is_hidden', false)
            ->whereHas('keywords', fn ($q) => $q->whereIn('keywords.id', $character->keywords->pluck('id'))
            )
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
            ]);

        return inertia('Characters/View', [
            'character' => $character,
            'miniature' => $miniature,
            'related_characters' => $relatedCharacters,
        ]);
    }

    public function random(Request $request): \Illuminate\Http\RedirectResponse
    {
        $character = Character::with('miniatures')->whereHas('miniatures')->inRandomOrder()->first();

        return redirect()->route('characters.view', ['character' => $character->slug, 'miniature' => $character->miniatures->first()->id, 'slug' => $character->miniatures->first()->slug]);
    }
}
