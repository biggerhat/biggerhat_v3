<?php

namespace App\Http\Middleware;

use App\Enums\PermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if ($user->hasAnyPermission(self::adminEntryPermissions())) {
            return $next($request);
        }

        abort(403);
    }

    /**
     * Permissions that grant access to the admin area.
     *
     * @return list<string>
     */
    public static function adminEntryPermissions(): array
    {
        // Any "view_*" permission lets a user see at least one admin index.
        // Blog authoring permissions also open the admin area (blog routes
        // use create_posts|edit_posts without a separate view_* perm).
        $permissions = array_filter(
            array_map(fn (PermissionEnum $case) => $case->value, PermissionEnum::cases()),
            fn (string $value) => str_starts_with($value, 'view_')
                || in_array($value, ['create_posts', 'edit_posts', 'manage_all_posts'], true),
        );

        return array_values($permissions);
    }
}
