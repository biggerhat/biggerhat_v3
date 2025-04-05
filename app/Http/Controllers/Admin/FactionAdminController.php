<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faction;
use Illuminate\Http\Request;

class FactionAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Factions/Index', [
            'factions' => Faction::all(),
        ]);
    }
}
