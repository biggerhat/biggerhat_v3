<?php

namespace App\Http\Controllers\API;

use App\Enums\CharacterStationEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\CrewUpgradeApiResource;
use App\Models\Character;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Builder;
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

        $upgrades = Upgrade::forCrews()
            ->whereHas('masters', function (Builder $query2) use ($name) {
                $query2->where('station', CharacterStationEnum::Master->value)
                    ->where('display_name', 'LIKE', "%{$name}%")
                    ->orWhere('nicknames', 'LIKE', "%{$name}%");
            })
            ->orWhere('name', 'LIKE', "%{$name}%")
            ->with('masters.miniatures')
            ->get();

        return CrewUpgradeApiResource::collection($upgrades)->toArray($request);
    }
}
