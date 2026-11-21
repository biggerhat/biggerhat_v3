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

        return Marker::whereSearch('name', $name)->get()->map(function (Marker $marker) {
            $marker->description = str_replace(['{{', '}}'], '**', $marker->description);

            return $marker;
        });
    }
}
