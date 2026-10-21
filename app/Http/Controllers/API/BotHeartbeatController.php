<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\BotHeartbeatRequest;
use App\Models\BotHeartbeat;
use Illuminate\Http\JsonResponse;

class BotHeartbeatController extends Controller
{
    public function __invoke(BotHeartbeatRequest $request): JsonResponse
    {
        BotHeartbeat::create($request->validated() + ['reported_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
