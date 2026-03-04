<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        return inertia('Admin/Users/Index', [
            'users' => User::with('roles')->orderBy('name', 'ASC')->get(),
        ]);
    }

    public function edit(Request $request, User $user)
    {
        return inertia('Admin/Users/UserForm', [
            'user' => $user->loadMissing('roles'),
            'checked_roles' => $user->roles->pluck('name'),
            'roles' => Role::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        return to_route('admin.users.index')->withMessage($user->name.' updated successfully!');
    }

    public function delete(Request $request, User $user)
    {
        $userName = $user->name;

        $user->delete();

        return to_route('admin.users.index')->withMessage($userName.' has been deleted.');
    }
}
