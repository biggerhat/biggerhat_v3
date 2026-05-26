<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreWeeklyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'is_black_joker' => ['required', 'boolean'],
            'is_red_joker' => ['required', 'boolean'],
            'terrain_marker_def' => ['nullable', 'array'],
            'requires_placement' => ['required', 'boolean'],
            'is_one_time' => ['required', 'boolean'],
        ];
    }
}
