<?php

namespace App\Http\Requests\Admin;

use App\Enums\FactionEnum;
use App\Models\CardArt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardArtRequest extends FormRequest
{
    /**
     * Route middleware (role:super_admin) gates access; this request only validates.
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
        $existing = $this->route('cardArt');

        return [
            'name' => ['required', 'string', 'max:120'],
            'faction' => ['nullable', 'string', Rule::enum(FactionEnum::class)],
            'category' => ['nullable', 'string', 'max:60'],
            'credit' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            // The image is required when creating; on edit it's only replaced if a new file is sent.
            'image' => [$existing instanceof CardArt ? 'nullable' : 'required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ];
    }
}
