<?php

namespace App\Http\Requests\Admin\Cooperative;

use App\Enums\CoopRoleEnum;
use App\Enums\PermissionEnum;
use App\Models\Cooperative\CoopClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoopClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionEnum::EditCampaignCatalog->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $existing = $this->route('coopClass');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'alpha_dash', Rule::unique('coop_classes', 'slug')->ignore($existing instanceof CoopClass ? $existing->id : null)],
            'role' => ['required', Rule::enum(CoopRoleEnum::class)],
            'is_unique' => ['required', 'boolean'],
            'resistance' => ['required', 'integer', 'min:0', 'max:30'],
            'speed' => ['nullable', 'integer', 'min:0', 'max:30'],
            'max_health' => ['required', 'integer', 'min:1', 'max:60'],
            'elite_max_health' => ['nullable', 'integer', 'min:1', 'max:60'],
            'abilities' => ['nullable', 'array', 'max:12'],
            'abilities.*.name' => ['required', 'string', 'max:120'],
            'abilities.*.text' => ['required', 'string', 'max:2000'],
            'actions' => ['nullable', 'array', 'max:12'],
            'actions.*.kind' => ['required', 'in:attack,tactical'],
            'actions.*.name' => ['required', 'string', 'max:120'],
            'actions.*.rg' => ['nullable', 'string', 'max:20'],
            'actions.*.skl' => ['nullable', 'string', 'max:20'],
            'actions.*.rst' => ['nullable', 'string', 'max:20'],
            'actions.*.tn' => ['nullable', 'string', 'max:20'],
            'actions.*.dmg' => ['nullable', 'string', 'max:20'],
            'actions.*.text' => ['nullable', 'string', 'max:2000'],
            'actions.*.triggers' => ['nullable', 'array', 'max:8'],
            'actions.*.triggers.*.name' => ['required', 'string', 'max:120'],
            'actions.*.triggers.*.text' => ['required', 'string', 'max:2000'],
            'behavior' => ['nullable', 'array', 'max:12'],
            'behavior.*.flip_min' => ['nullable', 'integer', 'min:1', 'max:13'],
            'behavior.*.flip_max' => ['nullable', 'integer', 'min:1', 'max:13', 'gte:behavior.*.flip_min'],
            'behavior.*.ideal_range' => ['nullable', 'string', 'max:20'],
            'behavior.*.effects' => ['required', 'array', 'min:1', 'max:10'],
            'behavior.*.effects.*' => ['required', 'string', 'max:200'],
            'target_preference' => ['nullable', 'array', 'max:6'],
            'target_preference.*' => ['required', 'string', 'max:80'],
            'elite_modifications' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [...$this->validated(), 'sort_order' => $this->validated('sort_order') ?? 0];
    }
}
