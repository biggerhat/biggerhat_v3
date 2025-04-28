<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Illuminate\Http\Request;

class SchemeAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Scheme::where('name', 'LIKE', "%{$name}%")->get();
    }
}
