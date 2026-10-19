<?php

namespace App\Http\Requests\Admin;

use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Miniature;
use App\Models\Package;
use App\Rules\SingleMatch;
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
            'characters.*' => ['string', new SingleMatch(Character::class, 'display_name')],
            'miniatures' => ['nullable', 'array'],
            'miniatures.*' => ['string', new SingleMatch(Miniature::class, 'display_name')],
            'packages' => ['nullable', 'array'],
            'packages.*' => ['string', new SingleMatch(Package::class, 'name')],
        ];
    }
}
