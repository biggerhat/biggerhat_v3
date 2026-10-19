<?php

namespace App\Http\Requests\CrewBuilder;

use App\Enums\FactionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrewBuilderUpdateRequest extends FormRequest
{
    /**
     * Ownership and policy checks stay in the controller; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|array',
            'faction' => ['sometimes', Rule::enum(FactionEnum::class)],
            'master_id' => 'sometimes|exists:characters,id',
            'encounter_size' => 'sometimes|integer|min:1',
            'crew_data' => 'sometimes|array',
            'custom_crew_data' => 'nullable|array',
            'miniature_selections' => 'nullable|array',
            'crew_upgrade_id' => 'nullable|exists:upgrades,id',
            'is_archived' => 'sometimes|boolean',
            'is_public' => 'sometimes|boolean',
            'custom_references' => 'nullable|array',

        ];
    }
}
