<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleEnum;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RenameUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(RoleEnum::SuperAdmin->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(User::class, 'name')->ignore($target->id),
                // The slug is derived from the name and is unique too, so
                // "Foo Bar" and "foo-bar" can't coexist.
                function (string $attribute, mixed $value, Closure $fail) use ($target) {
                    $slug = Str::slug((string) $value);

                    if ($slug === '') {
                        $fail('The name must contain at least one letter or number.');
                    } elseif (User::where('slug', $slug)->whereKeyNot($target->id)->exists()) {
                        $fail('That name is too similar to an existing user name.');
                    }
                },
            ],
        ];
    }
}
