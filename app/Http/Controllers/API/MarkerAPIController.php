<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Marker;
use Illuminate\Http\Request;

class MarkerAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Marker::where('name', 'LIKE', "%{$name}%")->get();
    }
}
