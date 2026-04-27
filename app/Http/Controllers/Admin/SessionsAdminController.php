<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Inertia\ResponseFactory;

class SessionsAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        // Database session driver — read directly. Group rows by user so the
        // UI can show "logged in 3 places" rather than 3 separate cards.
        $rows = DB::table('sessions')
            ->orderByDesc('last_activity')
            ->get();

        $userIds = $rows->pluck('user_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->select('id', 'name', 'email')->get()->keyBy('id');

        $sessions = $rows->map(fn ($row) => [
            'id' => $row->id,
            'user' => $row->user_id ? ($users->get($row->user_id)
                ? ['id' => $users[$row->user_id]->id, 'name' => $users[$row->user_id]->name, 'email' => $users[$row->user_id]->email]
                : null) : null,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'last_activity' => $row->last_activity,
            'last_activity_iso' => $row->last_activity ? date('c', $row->last_activity) : null,
            // Surface "this is your session" so the UI can warn before self-revoke.
            'is_current' => $row->id === $request->session()->getId(),
        ])->values();

        return inertia('Admin/Sessions/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        DB::table('sessions')->where('id', $id)->delete();

        return back()->withMessage('Session revoked.');
    }

    public function destroyAllForUser(Request $request, int $userId): RedirectResponse
    {
        $deleted = DB::table('sessions')->where('user_id', $userId)->delete();

        return back()->withMessage("{$deleted} session(s) revoked.");
    }
}
