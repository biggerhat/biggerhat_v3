<?php

namespace App\Http\Controllers\API;

use App\Enums\CharacterStationEnum;
use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Upgrade;
use Illuminate\Http\Request;

class UpgradeAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Upgrade::where('name', 'LIKE', "%{$name}%")->get();
    }

    public function crew(Request $request)
    {
        $name = $request->get('name');

        return Character::where('station', CharacterStationEnum::Master->value)
            ->where('display_name', 'LIKE', "%{$name}%")
            ->orWhere('nicknames', 'LIKE', "%{$name}%")
            ->whereHas('crewUpgrades')
            ->with('crewUpgrades')
            ->get();
    }
}
