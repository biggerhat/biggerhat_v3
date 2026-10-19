<?php

namespace App\Http\Requests\Admin;

use App\Enums\FactionEnum;
use App\Enums\PodSourceEnum;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\Upgrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PodLinkRequest extends FormRequest
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
            'source' => ['required', 'string', Rule::enum(PodSourceEnum::class)],
            'url' => ['required', 'url', 'max:2048'],
            'miniatures' => ['nullable', 'array'],
            'miniatures.*' => ['integer', Rule::exists(Miniature::class, 'id')],
            'upgrades' => ['nullable', 'array'],
            'upgrades.*' => ['string', Rule::exists(Upgrade::class, 'slug')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', Rule::exists(Keyword::class, 'slug')],
            'factions' => ['nullable', 'array'],
            'factions.*' => ['string', Rule::enum(FactionEnum::class)],
        ];
    }
}
