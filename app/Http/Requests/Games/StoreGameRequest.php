<?php

namespace App\Http\Requests\Games;

use App\Enums\GameFormatEnum;
use App\Enums\GamePlayModeEnum;
use App\Enums\PoolSeasonEnum;
use App\Models\TabletopLayout;
use App\Support\Tabletop\TabletopAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            // Bonanza Brawl uses 11ss; standard uses 35/40/50. Min reduced from 20
            // to 10 to fit Bonanza without rejecting it; max kept generous for
            // homebrew formats (Henchman Hardcore, narrative, etc).
            'encounter_size' => ['required', 'integer', 'min:10', 'max:100'],
            'season' => ['required', 'string', Rule::enum(PoolSeasonEnum::class)],
            'is_solo' => ['sometimes', 'boolean'],
            'format' => ['sometimes', 'string', Rule::enum(GameFormatEnum::class)],
            // how it will be played; the tabletop needs access to it, and a table can only be one of the player's own
            'play_mode' => ['nullable', 'string', Rule::enum(GamePlayModeEnum::class), function (string $attribute, mixed $value, \Closure $fail) {
                if ($value === GamePlayModeEnum::Tabletop->value && ! TabletopAccess::allows($this->user())) {
                    $fail('The tabletop app is not available to you yet.');
                }
            }],
            // only for the tabletop (null is the empty table)
            'tabletop_layout' => [
                'nullable',
                'string',
                'prohibited_unless:play_mode,'.GamePlayModeEnum::Tabletop->value,
                Rule::exists(TabletopLayout::class, 'uuid')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
