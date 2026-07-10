<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\CharacterStationEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrewCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'master_id' => ['nullable', 'integer', Rule::exists('characters', 'id')->where('station', CharacterStationEnum::Master->value)],
            'requires_token_choice' => ['required', 'boolean'],
            'requires_marker_choice' => ['required', 'boolean'],
            'requires_upgrade_type_choice' => ['required', 'boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.id' => ['required', 'integer', 'exists:actions,id'],
            'actions.*.is_signature' => ['required', 'boolean'],
            'ability_ids' => ['nullable', 'array'],
            'ability_ids.*' => ['integer', 'exists:abilities,id'],
        ];
    }
}
