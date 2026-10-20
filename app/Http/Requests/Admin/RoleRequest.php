<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Shared create/update validation for roles. Update is detected via the bound
 * `{role}` route parameter.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        // Only a super_admin may touch the super_admin role itself.
        if ($role && $role->name === RoleEnum::SuperAdmin->value) {
            return (bool) $this->user()?->hasRole(RoleEnum::SuperAdmin->value);
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $role ? Rule::unique('roles')->ignore($role->id) : Rule::unique('roles'),
                // Built-in roles are referenced by name all over the code
                // (hasRole('super_admin'), seeders, middleware) — renaming one
                // silently breaks those checks.
                function (string $attribute, mixed $value, Closure $fail) use ($role) {
                    if ($role && RoleEnum::tryFrom($role->name) && $value !== $role->name) {
                        $fail('Built-in roles cannot be renamed.');
                    }
                },
            ],
            'permissions' => [
                'required',
                'array',
                // A non-super_admin can only grant permissions they hold
                // themselves, so edit_role can't be used to self-escalate.
                function (string $attribute, mixed $value, Closure $fail) {
                    $user = $this->user();

                    if (! $user || $user->hasRole(RoleEnum::SuperAdmin->value)) {
                        return;
                    }

                    foreach ((array) $value as $permission) {
                        if (! $user->can($permission)) {
                            $fail('You cannot grant a permission you do not hold: '.$permission);

                            return;
                        }
                    }
                },
            ],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }
}
