<?php

namespace App\Http\Controllers\Database;

use App\Enums\FactionEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use Illuminate\Http\Request;

class FactionController extends Controller
{
    public function view(Request $request, FactionEnum $factionEnum)
    {
        return inertia('Factions/View', [
            'faction' => ['name' => $factionEnum->label(), 'color' => $factionEnum->color()],
            'characters' => Character::where('faction', $factionEnum->value)->get(),
        ]);
    }
}
