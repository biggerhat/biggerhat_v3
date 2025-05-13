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
        return inertia('Characters/View', [
            'character' => $character->loadMissing('miniatures', 'keywords', 'characteristics', 'crewUpgrades', 'totem.standardMiniatures', 'isTotemFor.standardMiniatures'),
            'miniature' => $miniature,
        ]);
    }

    public function random(Request $request): \Illuminate\Http\RedirectResponse
    {
        $character = Character::with('miniatures')->whereHas('miniatures')->inRandomOrder()->first();

        return redirect()->route('characters.view', ['character' => $character->slug, 'miniature' => $character->miniatures->first()->id, 'slug' => $character->miniatures->first()->slug]);
    }
}
