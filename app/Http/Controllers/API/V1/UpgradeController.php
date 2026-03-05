<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\UpgradeResource;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UpgradeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $upgrades = Upgrade::query()
            ->when($request->query('domain'), fn ($q, $domain) => $q->where('domain', $domain))
            ->when($request->query('faction'), fn ($q, $faction) => $q->where('faction', $faction))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return UpgradeResource::collection($upgrades);
    }

    public function show(Upgrade $upgrade): UpgradeResource
    {
        $upgrade->loadMissing(['keywords', 'actions.triggers', 'abilities', 'triggers', 'markers', 'tokens', 'characters']);

        return new UpgradeResource($upgrade);
    }
}
