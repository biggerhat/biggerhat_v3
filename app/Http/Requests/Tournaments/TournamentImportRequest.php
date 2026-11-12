<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\PoolSeasonEnum;
use App\Tournaments\Rulesets\RulesetRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TournamentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ruleset' => ['required', Rule::in(app(RulesetRegistry::class)->keys())],
            'name' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:255'],
            'encounter_size' => ['required', 'integer', 'min:1', 'max:1000'],
            'season' => ['nullable', Rule::enum(PoolSeasonEnum::class)],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $ruleset = app(RulesetRegistry::class)->resolve($this->input('ruleset'));
            $size = $ruleset->gameSize;
            $value = $this->input('encounter_size');
            if (is_numeric($value) && ((int) $value < $size['min'] || (int) $value > $size['max'])) {
                $validator->errors()->add('encounter_size', "The game size must be between {$size['min']} and {$size['max']}.");
            }
        }];
    }
}
