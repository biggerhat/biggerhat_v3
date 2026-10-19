<?php

namespace App\Http\Requests\Admin;

use App\Enums\GameSystemEnum;
use App\Enums\PackageCategoryEnum;
use App\Enums\SculptVersionEnum;
use App\Models\Character;
use App\Models\Keyword;
use App\Models\Miniature;
use App\Models\TOS\Unit;
use App\Rules\SingleMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'game_system' => ['nullable', 'string', Rule::enum(GameSystemEnum::class)],
            'category' => ['nullable', 'string', Rule::enum(PackageCategoryEnum::class)],
            'legacy_m3e_name' => ['nullable', 'string', 'max:255'],
            'factions' => ['nullable', 'array'],
            'factions.*' => ['string'],
            'sku' => ['nullable', 'string', 'max:255'],
            'upc' => ['nullable', 'string', 'max:255'],
            'msrp' => ['nullable', 'integer'],
            'distributor_description' => ['nullable', 'string'],
            'sculpt_version' => ['nullable', 'string', Rule::enum(SculptVersionEnum::class)],
            'is_preassembled' => ['nullable', 'boolean'],
            'released_at' => ['nullable', 'date'],
            'front_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'back_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'combination_image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'characters' => ['nullable', 'array'],
            'characters.*' => ['string', new SingleMatch(Character::class, 'display_name')],
            'character_quantities' => ['nullable', 'array'],
            'character_quantities.*' => ['nullable', 'integer', 'min:1'],
            'character_special_orders' => ['nullable', 'array'],
            'character_special_orders.*' => ['nullable', 'boolean'],
            'miniatures' => ['nullable', 'array'],
            'miniatures.*' => ['string', new SingleMatch(Miniature::class, 'display_name')],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', new SingleMatch(Keyword::class, 'name')],
            // Keyed by slug — TOS Unit names collide across title variants.
            'tos_units' => ['nullable', 'array'],
            'tos_units.*' => ['string', Rule::exists(Unit::class, 'slug')],
        ];
    }
}
