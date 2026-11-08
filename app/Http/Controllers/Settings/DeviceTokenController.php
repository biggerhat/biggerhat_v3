<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

/**
 * Called by the native app shell to register / drop its push token (docs/plans/native-app.md). JSON only — call
 * with fetch, not an Inertia visit.
 */
class DeviceTokenController extends Controller
{
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        // One row per token: a device that signs in as someone else (or reinstalls) moves to the new owner.
        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'last_seen_at' => now()],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(DeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::where('user_id', $request->user()->id)->where('token', $request->validated('token'))->delete();

        return response()->json(['ok' => true]);
    }
}
