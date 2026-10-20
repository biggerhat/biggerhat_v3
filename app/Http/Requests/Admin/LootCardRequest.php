<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LootCardRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'title_a' => ['nullable', 'string', 'max:255'],
            'title_b' => ['nullable', 'string', 'max:255'],
            'effect_a' => ['nullable', 'string', 'max:5000'],
            'effect_b' => ['nullable', 'string', 'max:5000'],
            'side_a_actions' => ['nullable', 'array'],
            'side_a_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_a_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            'side_b_actions' => ['nullable', 'array'],
            'side_b_actions.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'side_b_actions.*.is_signature_action' => ['sometimes', 'boolean'],
            'side_a_abilities' => ['nullable', 'array'],
            'side_a_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_b_abilities' => ['nullable', 'array'],
            'side_b_abilities.*' => ['integer', 'exists:abilities,id'],
            'side_a_triggers' => ['nullable', 'array'],
            'side_a_triggers.*' => ['integer', 'exists:triggers,id'],
            'side_b_triggers' => ['nullable', 'array'],
            'side_b_triggers.*' => ['integer', 'exists:triggers,id'],
            'image' => ['nullable', 'file', 'max:30000', 'mimes:heic,jpeg,jpg,png,webp'],
            'remove_image' => ['sometimes', 'boolean'],
        ];

        // Suit/value are fixed once a card exists.
        if ($this->routeIs('admin.loot_cards.store')) {
            $rules['suit'] = ['required', 'string', 'in:crow,mask,ram,tome,joker'];
            $rules['value'] = ['nullable', 'integer', 'min:1', 'max:13'];
            $rules['value_label'] = ['nullable', 'string', 'max:32'];
        }

        return $rules;
    }
}
