<?php

namespace App\Http\Requests\TOS\Admin;

use App\Enums\TOS\AssetLimitParameterTypeEnum;
use App\Enums\TOS\AssetLimitTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_tos_asset') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scrip_cost' => ['required', 'integer'],
            'disable_count' => ['nullable', 'integer', 'min:0'],
            'scrap_count' => ['nullable', 'integer', 'min:0'],
            'body' => ['nullable', 'string'],
            'image_path' => ['nullable', 'file', 'image', 'max:30000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'allegiance_ids' => ['nullable', 'array'],
            'allegiance_ids.*' => ['integer', 'exists:tos_allegiances,id'],
            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:tos_abilities,id'],
            'action_ids' => ['nullable', 'array'],
            'action_ids.*' => ['integer', 'exists:tos_actions,id'],

            'limits' => ['nullable', 'array'],
            'limits.*.limit_type' => ['required_with:limits', 'string', Rule::enum(AssetLimitTypeEnum::class)],
            'limits.*.parameter_type' => ['nullable', 'string', Rule::enum(AssetLimitParameterTypeEnum::class)],
            'limits.*.parameter_value' => ['nullable', 'string', 'max:255'],
            'limits.*.parameter_unit_id' => ['nullable', 'integer', 'exists:tos_units,id'],
            'limits.*.parameter_allegiance_id' => ['nullable', 'integer', 'exists:tos_allegiances,id'],
            'limits.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
