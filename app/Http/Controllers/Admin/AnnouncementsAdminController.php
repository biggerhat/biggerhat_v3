<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;
use Inertia\ResponseFactory;

class AnnouncementsAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        if (! Schema::hasTable('announcements')) {
            return inertia('Admin/Announcements/Index', [
                'announcements' => [],
                'storage_ready' => false,
            ]);
        }

        $announcements = Announcement::with('createdBy:id,name')
            ->latest()
            ->limit(100)
            ->get()
            ->map(function (Announcement $a) {
                return [
                    'id' => $a->id,
                    'message' => $a->message,
                    'level' => $a->level,
                    'audience' => $a->audience,
                    'starts_at' => $a->starts_at?->toIso8601String(),
                    'ends_at' => $a->ends_at?->toIso8601String(),
                    'is_dismissable' => (bool) $a->is_dismissable,
                    'link_url' => $a->link_url,
                    'link_label' => $a->link_label,
                    'created_by' => $a->createdBy ? ['id' => $a->createdBy->id, 'name' => $a->createdBy->name] : null,
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            });

        return inertia('Admin/Announcements/Index', [
            'announcements' => $announcements,
            'storage_ready' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by_id'] = $request->user()?->id;
        Announcement::create($data);

        return back()->withMessage('Announcement created.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return back()->withMessage('Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->withMessage('Announcement deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'level' => ['required', 'in:info,warning,success'],
            'audience' => ['required', 'in:all,authenticated,super_admin'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_dismissable' => ['boolean'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:64'],
        ]);
    }
}
