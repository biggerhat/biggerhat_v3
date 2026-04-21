<?php

namespace App\Http\Requests\Games;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;

class SubmitMasterRequest extends FormRequest
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
            'master_name' => ['required', 'string', 'max:255'],
            'slot' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
