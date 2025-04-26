<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Miniature;
use Illuminate\Http\Request;

class CharacterAPIController extends Controller
{
    public function find(Request $request)
    {
        $name = $request->get('name');

        return Miniature::where('display_name', 'LIKE', "%{$name}%")
            ->orWhereHas('character', function ($query) use ($name) {
                $query->where('nicknames', 'LIKE', "%{$name}%");
            })
            ->with('character')->get();
    }
}
