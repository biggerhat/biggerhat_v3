<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreCrewCardEffectRequest extends FormRequest
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
            'requires_token_choice' => ['required', 'boolean'],
            'requires_marker_choice' => ['required', 'boolean'],
            'requires_upgrade_type_choice' => ['required', 'boolean'],
            'restrictions' => ['nullable', 'array'],
            'grants_ability' => ['nullable', 'array'],
            'grants_action' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
