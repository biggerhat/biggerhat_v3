<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class GamePlaySelectLootRequest extends FormRequest
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
            'game_crew_member_id' => ['required', 'integer', 'exists:game_crew_members,id'],
            'loot_card_id' => ['required', 'integer', 'exists:loot_cards,id'],
            'side' => ['required', 'string', 'in:a,b'],

        ];
    }
}
