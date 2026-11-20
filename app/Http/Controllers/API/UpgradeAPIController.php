<?php

namespace App\Http\Controllers\API;

use App\Enums\CharacterStationEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\CrewUpgradeApiResource;
use App\Models\Upgrade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UpgradeAPIController extends Controller
{
    public function view(Request $request)
    {
        $name = $request->get('name');

        return Upgrade::standard()->whereSearch('name', $name)->get();
    }

    public function crew(Request $request)
    {
        $name = $request->get('name');

        $upgrades = Upgrade::standard()->forCrews()
            ->where(function ($query) use ($name) {
                $query->whereHas('masters', function (Builder $query2) use ($name) {
                    $query2->where('station', CharacterStationEnum::Master->value)
                        ->where(function ($q) use ($name) {
                            $q->whereSearch('display_name', $name)
                                ->orWhereSearch('nicknames', $name);
                        });
                })->orWhereSearch('name', $name);
            })
            ->with('masters.miniatures')
            ->get();

        return CrewUpgradeApiResource::collection($upgrades)->toArray($request);
    }
}
