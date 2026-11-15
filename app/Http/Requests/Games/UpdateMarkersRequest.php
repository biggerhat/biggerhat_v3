<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * Markers are placed, moved and picked up by either player. A request upserts some and removes some, so two players
     * working at once do not replace each other's markers. Only the marker's id on the table and its kind are sent: its
     * name and base size always come from the markers table.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'upsert' => ['sometimes', 'array', 'max:20'],
            'upsert.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'upsert.*.marker_id' => ['required', 'integer', 'exists:markers,id'],
            'upsert.*.x' => ['required', 'numeric', 'between:0,36'],
            'upsert.*.y' => ['required', 'numeric', 'between:0,36'],
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
