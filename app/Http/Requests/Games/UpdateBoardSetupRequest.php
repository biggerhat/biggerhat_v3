<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('game')) ?? false;
    }

    /**
     * Which of the four ways round the deployment zones sit (who is in which corner or edge). Either player may set it:
     * the two of them agree it at the table.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['deployment_variant' => ['required', 'integer', 'between:0,3']];
    }
}
