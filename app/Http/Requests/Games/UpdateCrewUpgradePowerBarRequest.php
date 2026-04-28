<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCrewUpgradePowerBarRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Game|null $game */
        $game = $this->route('game');

        return $game !== null && $this->user()?->can('view', $game) === true;
    }

    public function rules(): array
    {
        return [
            'upgrade_id' => ['required', 'integer', 'exists:upgrades,id'],
            'current_power_bar' => ['required', 'integer', 'min:0', 'max:99'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
