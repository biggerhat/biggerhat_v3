<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\ChannelResource;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChannelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $channels = Channel::query()
            ->withCount('transmissions')
            ->when($request->query('search'), fn ($q, $search) => $q->where('name', 'LIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return ChannelResource::collection($channels);
    }

    public function show(Channel $channel): ChannelResource
    {
        $channel->loadMissing(['transmissions' => fn ($q) => $q->with(['characters', 'keywords'])->latest('release_date')]);
        $channel->loadCount('transmissions');

        return new ChannelResource($channel);
    }
}
