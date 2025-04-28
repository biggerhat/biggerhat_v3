<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Strategy;
use Illuminate\Http\Request;

class StrategyAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Strategy::where('name', 'LIKE', "%{$name}%")->get();
    }
}
