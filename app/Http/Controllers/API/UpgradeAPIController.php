<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Upgrade;
use Illuminate\Http\Request;

class UpgradeAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Upgrade::where('name', 'LIKE', "%{$name}%")->get();
    }
}
