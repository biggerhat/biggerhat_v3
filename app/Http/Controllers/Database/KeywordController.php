<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use Illuminate\Http\Request;

class KeywordController extends Controller
{
    public function view(Request $request, Keyword $keyword)
    {
        dd($keyword->name);

        return inertia('Keywords/View', [
            'keyword' => $keyword->loadMissing('characters.miniatures'),
        ]);
    }
}
