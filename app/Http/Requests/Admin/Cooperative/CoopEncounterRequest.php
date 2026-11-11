<?php

namespace App\Http\Requests\Admin\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Enums\PermissionEnum;
use App\Models\Cooperative\CoopEncounter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoopEncounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $existing = $this->route('coopEncounter');
        $ignore = $existing instanceof CoopEncounter ? $existing->id : null;
        $lists = ['nullable', 'array', 'max:30'];

        return [
            'number' => ['required', 'integer', 'min:0', 'max:200', Rule::unique('coop_encounters', 'number')->ignore($ignore)],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'alpha_dash', Rule::unique('coop_encounters', 'slug')->ignore($ignore)],
            'narrative' => ['nullable', 'string', 'max:4000'],
            'encounter_size' => ['nullable', 'integer', 'min:0', 'max:200'],
            'max_hand_size' => ['nullable', 'integer', 'min:0', 'max:30'],
            'board_size' => ['nullable', 'string', 'max:40'],
            'challenge_mode' => ['nullable', 'string', 'max:4000'],
            'phases' => ['nullable', 'array', 'max:12'],
            'phases.*.title' => ['required', 'string', 'max:160'],
            'phases.*.narrative' => ['nullable', 'string', 'max:4000'],
            'phases.*.max_hand_size' => ['nullable', 'integer', 'min:0', 'max:30'],
            'phases.*.role_order' => ['nullable', 'array', 'max:5'],
            'phases.*.role_order.*.role' => ['required', Rule::enum(CoopRoleEnum::class)],
            'phases.*.role_order.*.class_slug' => ['nullable', 'string', 'max:140', Rule::exists('coop_classes', 'slug')],
            'phases.*.setup' => $lists,
            'phases.*.setup.*' => ['required', 'string', 'max:2000'],
            'phases.*.special_rules' => $lists,
            'phases.*.special_rules.*' => ['required', 'string', 'max:3000'],
            'phases.*.victory' => $lists,
            'phases.*.victory.*' => ['required', 'string', 'max:2000'],
            'phases.*.defeat' => $lists,
            'phases.*.defeat.*' => ['required', 'string', 'max:2000'],
            'phases.*.progression' => $lists,
            'phases.*.progression.*' => ['required', 'string', 'max:2000'],
            'campaign' => ['nullable', 'array'],
            'campaign.victory' => ['nullable', 'string', 'max:3000'],
            'campaign.defeat' => ['nullable', 'string', 'max:3000'],
            'campaign.aftermath' => $lists,
            'campaign.aftermath.*' => ['required', 'string', 'max:2000'],
            'campaign.next_encounter' => ['nullable', 'string', 'max:3000'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
