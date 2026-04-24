<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AllegianceTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStratagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_stratagem') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'allegiance_id' => ['nullable', 'integer', 'exists:tos_allegiances,id'],
            'allegiance_type' => ['nullable', 'string', Rule::enum(AllegianceTypeEnum::class)],
            'tactical_cost' => ['required', 'integer', 'min:1'],
            'effect' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
