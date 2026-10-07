<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\TransmissionTypeEnum;
use App\Models\Character;
use App\Models\Keyword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransmissionRequest extends FormRequest
{
    /**
     * Route middleware (role:/permission:) gates access; this request only validates.
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'url', 'max:500'],
            'channel_id' => ['required', 'integer', 'exists:channels,id'],
            'transmission_type' => ['required', 'string', Rule::enum(TransmissionTypeEnum::class)],
            'content_type' => ['required', 'string', Rule::enum(ContentTypeEnum::class)],
            'factions' => ['nullable', 'array'],
            'factions.*' => ['string', Rule::enum(FactionEnum::class)],
            'release_date' => ['nullable', 'date'],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', Rule::exists(Character::class, 'slug')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', Rule::exists(Keyword::class, 'slug')],
        ];
    }
}
