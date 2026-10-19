<?php

namespace App\Http\Requests\Admin;

use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlueprintRequest extends FormRequest
{
    /**
     * Route middleware (permission:edit_*) gates access; this request only validates.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'string', 'max:500'],
            'sculpt_version' => ['required', 'string', Rule::enum(SculptVersionEnum::class)],
            'published_at' => ['nullable', 'date'],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', Rule::exists(Character::class, 'display_name')],
            'miniatures' => ['nullable', 'array'],
            'miniatures.*' => ['string', Rule::exists(Miniature::class, 'display_name')],
            'packages' => ['nullable', 'array'],
            'packages.*' => ['string', Rule::exists(Package::class, 'name')],
        ];
    }
}
