<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\ActionTypeEnum;
use App\Enums\TOS\UsageLimitEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_action') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', Rule::enum(ActionTypeEnum::class)],
            'av' => ['nullable', 'integer', 'min:0', 'max:255'],
            'av_target' => ['nullable', 'string', 'max:8'],
            'av_suits' => ['nullable', 'string', 'max:8'],
            'tn' => ['nullable', 'integer', 'min:0', 'max:255'],
            'range' => ['nullable', 'string', 'max:32'],
            'strength' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_piercing' => ['sometimes', 'boolean'],
            'is_accurate' => ['sometimes', 'boolean'],
            'is_area' => ['sometimes', 'boolean'],
            'usage_limit' => ['nullable', 'string', Rule::enum(UsageLimitEnum::class)],
            'body' => ['nullable', 'string'],
        ];
    }
}
