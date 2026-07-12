<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs both the header bell dropdown (recent()/markAsRead/markAllAsRead —
 * thin wrappers around Laravel's built-in Notifiable methods, User already
 * uses the Notifiable trait) and the full paginated history page (index(),
 * linked from the settings hub, same "cross-cutting page surfaced there but
 * not owned by it" treatment as Wishlists/My Stats).
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(20)
                ->through(fn ($n) => [
                    'id' => $n->id,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                    ...$n->data,
                ]),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->limit(20)->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
                ...$n->data,
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
