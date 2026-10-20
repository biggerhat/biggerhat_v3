<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageTypeEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenameUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(Request $request): \Inertia\Response|\Inertia\ResponseFactory
    {
        $search = trim($request->string('q')->toString());
        $role = $request->string('role')->toString();

        $users = User::query()
            ->select(['id', 'name', 'slug', 'email', 'email_verified_at', 'created_at'])
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return inertia('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['q' => $search, 'role' => $role],
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function edit(Request $request, User $user)
    {
        return inertia('Admin/Users/UserForm', [
            'user' => array_merge(
                $user->loadMissing('roles')->toArray(),
                ['supporter_since' => $user->supporter_since?->format('Y-m-d')],
            ),
            'checked_roles' => $user->roles->pluck('name'),
            'roles' => Role::orderBy('name', 'ASC')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $rolesBefore = $user->roles->pluck('name')->sort()->values()->all();

        $user->syncRoles($validated['roles'] ?? []);
        $user->update(['supporter_since' => $validated['supporter_since'] ?? null]);

        $rolesAfter = collect($validated['roles'] ?? [])->sort()->values()->all();
        if ($rolesBefore !== $rolesAfter) {
            AdminAudit::log('Changed roles for '.$user->name, $user, [
                'added' => array_values(array_diff($rolesAfter, $rolesBefore)),
                'removed' => array_values(array_diff($rolesBefore, $rolesAfter)),
            ]);
        }

        return to_route('admin.users.index')->withMessage($user->name.' updated successfully!');
    }

    public function rename(RenameUserRequest $request, User $user)
    {
        $oldName = $user->name;
        $user->update(['name' => $request->validated()['name']]);

        // The slug follows the name, so the old edit URL is dead — redirect to the new one.
        return to_route('admin.users.edit', $user->slug)->withMessage($oldName.' renamed to '.$user->name.'.');
    }

    public function generatePasswordResetLink(Request $request, User $user)
    {
        AdminAudit::log('Generated password reset link for '.$user->name, $user);
        $token = Password::broker()->createToken($user);
        $resetUrl = url(route('password.reset', ['token' => $token, 'email' => $user->email], false));

        return back()->with('reset_link', $resetUrl);
    }

    public function delete(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withMessage('You cannot delete your own account.', null, MessageTypeEnum::error);
        }

        if ($user->hasRole(RoleEnum::SuperAdmin->value)) {
            return back()->withMessage('Super admins cannot be deleted from here — remove the role first.', null, MessageTypeEnum::error);
        }

        $userName = $user->name;

        AdminAudit::log('Deleted user '.$userName, null, ['user_id' => $user->id, 'email' => $user->email]);
        $user->delete();

        return to_route('admin.users.index')->withMessage($userName.' has been deleted.');
    }
}
