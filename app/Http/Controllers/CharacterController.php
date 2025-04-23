<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Miniature;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class CharacterController extends Controller
{
    public function view(Request $request, Character $character, Miniature $miniature): Response|ResponseFactory
    {
        return inertia('Characters/Index', [
            'character' => $character->loadMissing('miniatures', 'keywords', 'characteristics'),
            'miniature' => $miniature,
        ]);
    }
}
