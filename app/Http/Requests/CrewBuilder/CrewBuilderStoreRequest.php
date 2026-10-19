<?php

namespace App\Http\Requests\CrewBuilder;

use App\Enums\FactionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrewBuilderStoreRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|array',
            'faction' => ['required', Rule::enum(FactionEnum::class)],
            'master_id' => 'required|exists:characters,id',
            'encounter_size' => 'required|integer|min:1',
            'crew_data' => 'present|array',
            'custom_crew_data' => 'nullable|array',
            'miniature_selections' => 'nullable|array',
            'crew_upgrade_id' => 'nullable|exists:upgrades,id',
            'copied_from_id' => 'nullable|exists:crew_builds,id',
            'custom_references' => 'nullable|array',

        ];
    }
}
