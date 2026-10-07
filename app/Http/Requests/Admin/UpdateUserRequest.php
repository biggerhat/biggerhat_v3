<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleEnum;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'roles' => [
                'nullable',
                'array',
                // Only a super_admin may grant or revoke the super_admin role —
                // otherwise anyone holding edit_user could promote themselves.
                function (string $attribute, mixed $value, Closure $fail) use ($target) {
                    if ($this->user()?->hasRole(RoleEnum::SuperAdmin->value)) {
                        return;
                    }

                    $requested = in_array(RoleEnum::SuperAdmin->value, (array) $value, true);

                    if ($requested !== $target->hasRole(RoleEnum::SuperAdmin->value)) {
                        $fail('Only a super admin can change the super admin role.');
                    }
                },
            ],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'supporter_since' => ['nullable', 'date'],
        ];
    }
}
