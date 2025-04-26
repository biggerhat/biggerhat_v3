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
            'character' => $character->loadMissing('miniatures', 'keywords', 'characteristics'),
            'miniature' => $miniature,
        ]);
    }
}
