<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllegianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_allegiance') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'type' => ['required', 'string', Rule::enum(AllegianceTypeEnum::class)],
            'is_syndicate' => ['required', 'boolean'],
            'description' => ['nullable', 'string'],
            'logo_path' => ['nullable', 'file', 'image', 'max:30000'],
            'color_slug' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
