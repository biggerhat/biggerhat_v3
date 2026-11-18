<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAurasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * Only auras a player chose to show the other player are saved with the game. `owner_id` is the model (its crew id) or the
     * marker (its id on the table) that casts it. A request upserts some and removes some, so two players working at once do not
     * replace each other's auras.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'upsert' => ['sometimes', 'array', 'max:20'],
            'upsert.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'upsert.*.owner_id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'upsert.*.range' => ['required', 'numeric', 'between:0.5,36'],
            'upsert.*.locked' => ['required', 'boolean'],
            // Terrain traits the area counts as (aura token terrain, p.57).
            'upsert.*.traits' => ['nullable', 'array', 'max:4'],
            'upsert.*.traits.*' => ['string', 'in:concealing,dense,hazardous,severe', 'distinct'],
            'upsert.*.x' => ['nullable', 'required_if:upsert.*.locked,true', 'numeric', 'between:0,36'],
            'upsert.*.y' => ['nullable', 'required_if:upsert.*.locked,true', 'numeric', 'between:0,36'],
            'remove' => ['sometimes', 'array', 'max:20'],
            'remove.*' => ['string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                if (empty($this->input('upsert')) && empty($this->input('remove'))) {
                    $validator->errors()->add('upsert', 'Nothing to change.');
                }
            },
        ];
    }
}
