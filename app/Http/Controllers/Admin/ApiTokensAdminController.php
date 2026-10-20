<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiTokenRequest;
use App\Models\User;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Response;
use Inertia\ResponseFactory;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokensAdminController extends Controller
{
    public function index(Request $request): Response|ResponseFactory
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return inertia('Admin/ApiTokens/Index', [
                'tokens' => [],
                'users' => User::query()->select('id', 'name', 'email')->orderBy('name')->get(),
                'new_token' => null,
                'storage_ready' => false,
            ]);
        }

        $tokens = PersonalAccessToken::query()
            ->with(['tokenable' => fn ($q) => $q->select('id', 'name', 'email')])
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
                'tokenable' => $t->tokenable instanceof User
                    ? ['id' => $t->tokenable->id, 'name' => $t->tokenable->name, 'email' => $t->tokenable->email]
                    : null,
            ]);

        return inertia('Admin/ApiTokens/Index', [
            'tokens' => $tokens,
            'users' => User::query()->select('id', 'name', 'email')->orderBy('name')->get(),
            // Surface the freshly-minted plain text token once via flash so the
            // Vue page can show it for copy. Never persisted client-side.
            'new_token' => session('new_api_token'),
            'storage_ready' => true,
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /** @var User $user */
        $user = User::findOrFail($data['user_id']);
        $abilities = $data['abilities'] ?? ['*'];
        $token = $user->createToken($data['name'], $abilities);

        AdminAudit::log('Created API token '.$data['name'].' for '.$user->name, $user, ['abilities' => $abilities]);

        return redirect()->route('admin.api_tokens.index')
            ->with('new_api_token', [
                'plaintext' => $token->plainTextToken,
                'name' => $data['name'],
                'user_name' => $user->name,
            ])
            ->withMessage('Token created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $token = PersonalAccessToken::find($id);
        if ($token) {
            AdminAudit::log('Revoked API token '.$token->name, null, ['token_id' => $token->id, 'tokenable_id' => $token->tokenable_id]);
            $token->delete();
        }

        return back()->withMessage('Token revoked.');
    }
}
