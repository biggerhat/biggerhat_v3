<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Inertia\ResponseFactory;

class CustomCardModerationAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        $type = $request->string('type')->toString() ?: 'all';
        $visibility = $request->string('visibility')->toString() ?: 'public';
        $search = $request->string('q')->toString() ?: null;

        $characters = collect();
        $upgrades = collect();

        if ($type === 'all' || $type === 'character') {
            $charQuery = CustomCharacter::query()
                ->with('user:id,name')
                ->when($visibility === 'public', fn ($q) => $q->where('is_public', true))
                ->when($visibility === 'private', fn ($q) => $q->where('is_public', false))
                ->when($search, fn ($q, $term) => $q->where(fn ($qq) => $qq->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('display_name', 'LIKE', "%{$term}%")))
                ->latest()
                ->limit(100)
                ->get();

            $characters = $charQuery->map(fn (CustomCharacter $c) => [
                'kind' => 'character',
                'id' => $c->id,
                'share_code' => $c->share_code,
                'name' => $c->display_name ?? $c->name,
                'faction' => $c->faction->value,
                'is_public' => (bool) $c->is_public,
                'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
                'created_at' => $c->created_at?->toIso8601String(),
                'share_url' => route('tools.card_creator.share', $c->share_code),
                'edit_url' => $c->user_id ? null : null, // Edit goes through user — admin doesn't edit directly
            ]);
        }

        if ($type === 'all' || $type === 'upgrade') {
            $upgQuery = CustomUpgrade::query()
                ->with('user:id,name')
                ->when($visibility === 'public', fn ($q) => $q->where('is_public', true))
                ->when($visibility === 'private', fn ($q) => $q->where('is_public', false))
                ->when($search, fn ($q, $term) => $q->where(fn ($qq) => $qq->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('display_name', 'LIKE', "%{$term}%")))
                ->latest()
                ->limit(100)
                ->get();

            $upgrades = $upgQuery->map(fn (CustomUpgrade $u) => [
                'kind' => 'upgrade',
                'id' => $u->id,
                'share_code' => $u->share_code,
                'name' => $u->display_name ?? $u->name,
                'faction' => $u->faction?->value,
                'domain' => $u->domain,
                'is_public' => (bool) $u->is_public,
                'user' => $u->user ? ['id' => $u->user->id, 'name' => $u->user->name] : null,
                'created_at' => $u->created_at?->toIso8601String(),
                'share_url' => route('tools.card_creator.upgrades.share', $u->share_code),
            ]);
        }

        $cards = $characters->concat($upgrades)->sortByDesc('created_at')->values();

        return inertia('Admin/CustomCards/Index', [
            'cards' => $cards,
            'filters' => ['type' => $type, 'visibility' => $visibility, 'q' => $search],
            'counts' => [
                'public_characters' => CustomCharacter::where('is_public', true)->count(),
                'public_upgrades' => CustomUpgrade::where('is_public', true)->count(),
                'total_characters' => CustomCharacter::count(),
                'total_upgrades' => CustomUpgrade::count(),
            ],
        ]);
    }

    public function unpublish(Request $request, string $kind, int $id): RedirectResponse
    {
        $model = $this->resolve($kind, $id);
        if (! $model) {
            return back()->withMessage('Card not found.');
        }
        $model->update(['is_public' => false]);

        return back()->withMessage('Card unpublished.');
    }

    public function destroy(Request $request, string $kind, int $id): RedirectResponse
    {
        $model = $this->resolve($kind, $id);
        if (! $model) {
            return back()->withMessage('Card not found.');
        }
        // Soft-delete — both models use SoftDeletes, owner can recover via support if needed.
        $model->delete();

        return back()->withMessage('Card removed (soft-deleted).');
    }

    private function resolve(string $kind, int $id): CustomCharacter|CustomUpgrade|null
    {
        return match ($kind) {
            'character' => CustomCharacter::find($id),
            'upgrade' => CustomUpgrade::find($id),
            default => null,
        };
    }
}
