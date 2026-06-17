<?php

namespace App\Http\Requests\Admin\Campaign;

use App\Enums\FactionEnum;
use App\Enums\PermissionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTotemTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'faction' => ['required', Rule::enum(FactionEnum::class)],
            'station' => ['nullable', 'string', 'max:100'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:20'],
            'health' => ['required', 'integer', 'min:1', 'max:30'],
            'defense' => ['required', 'integer', 'min:1', 'max:10'],
            'defense_suit' => ['nullable', 'string', 'max:10'],
            'willpower' => ['required', 'integer', 'min:1', 'max:10'],
            'willpower_suit' => ['nullable', 'string', 'max:10'],
            'speed' => ['required', 'integer', 'min:1', 'max:10'],
            'size' => ['nullable', 'integer', 'min:1', 'max:5'],
            'base' => ['required', 'string', 'max:10'],
            'campaign_totem_flip_value' => ['nullable', 'integer', 'min:1', 'max:13'],
            'campaign_is_black_joker_totem' => ['required', 'boolean'],
            'campaign_is_red_joker_totem' => ['required', 'boolean'],
            'campaign_totem_special_replace' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
