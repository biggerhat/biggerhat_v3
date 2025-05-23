<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Http\Resources\MarkerResource;
use App\Models\Marker;
use Illuminate\Http\Request;

class MarkerController extends Controller
{
    public function index(Request $request)
    {
        $markers = Marker::orderBy('name', 'ASC')->get();

        return inertia('Markers/Index', [
            'markers' => MarkerResource::collection($markers)->toArray($request),
        ]);
    }
}
