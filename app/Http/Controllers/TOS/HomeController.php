<?php

namespace App\Http\Controllers\TOS;

use App\Http\Controllers\Controller;
use App\Models\TOS\Allegiance;

class HomeController extends Controller
{
    public function index()
    {
        return inertia('TOS/Index', [
            'allegiances' => Allegiance::query()
                ->mainAllegiances()
                ->orderBy('sort_order')
                ->get(),
            'syndicates' => Allegiance::query()
                ->syndicates()
                ->orderBy('name')
                ->get(),
        ]);
    }
}
