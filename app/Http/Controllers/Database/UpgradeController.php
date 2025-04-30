<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use App\Models\Upgrade;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function view(Request $request, Upgrade $upgrade)
    {
        dd($upgrade->name);

        return inertia('Upgrades/View', [
            'upgrade' => $upgrade,
        ]);
    }
}
