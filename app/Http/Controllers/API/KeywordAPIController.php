<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use Illuminate\Http\Request;

class KeywordAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Keyword::with('characters.standardMiniatures')
            ->where('name', 'LIKE', "%{$name}%")
            ->get();
    }
}
