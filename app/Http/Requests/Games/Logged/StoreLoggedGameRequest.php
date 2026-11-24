<?php

namespace App\Http\Requests\Games\Logged;

use App\Enums\DeploymentEnum;
use App\Enums\FactionEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GamePlayModeEnum;
use App\Enums\GameRoleEnum;
use App\Enums\PoolSeasonEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A game played and tracked somewhere else, entered by hand. Only the result, the two sides' faction, master and points are needed;
 * the turns, schemes and crews are the same things the Game Tracker records and are optional. Index 0 of `turns.*` is the turn
 * number's data for the player entering the game (`me`) and the opponent.
 */
class StoreLoggedGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $side = fn (string $key, bool $mine): array => [
            "$key.faction" => ['required', 'string', Rule::enum(FactionEnum::class)],
            "$key.master_id" => ['nullable', 'integer', 'exists:characters,id'],
            "$key.master_name" => [$mine ? 'required_without:me.master_id' : 'nullable', 'nullable', 'string', 'max:120'],
            "$key.role" => ['nullable', 'string', Rule::enum(GameRoleEnum::class)],
            "$key.total_points" => ['required', 'integer', 'min:0', 'max:99'],
            "$key.soulstone_pool" => ['nullable', 'integer', 'min:0', 'max:50'],
            "$key.models" => ['nullable', 'array', 'max:40'],
            "$key.models.*.character_id" => ['required', 'integer', 'exists:characters,id'],
            "$key.models.*.killed" => ['boolean'],
            "$key.models.*.cost" => ['nullable', 'integer', 'min:0', 'max:30'],
            "$key.models.*.hiring_category" => ['nullable', 'string', 'max:30'],
        ];

        return [
            'format' => ['required', 'string', Rule::enum(GameFormatEnum::class)],
            'played_on' => ['required', 'date', 'before_or_equal:today'],
            'play_mode' => ['nullable', 'string', Rule::enum(GamePlayModeEnum::class)],
            'season' => ['nullable', 'string', Rule::enum(PoolSeasonEnum::class)],
            'encounter_size' => ['required', 'integer', 'min:10', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:120'],
            'is_public' => ['boolean'],
            'strategy_id' => ['nullable', 'integer', 'exists:strategies,id'],
            'deployment' => ['nullable', 'string', Rule::enum(DeploymentEnum::class)],
            'scheme_pool' => ['nullable', 'array', 'max:10'],
            'scheme_pool.*' => ['integer', 'exists:schemes,id'],
            'result' => ['required', 'string', Rule::in(['win', 'loss', 'tie'])],

            'me' => ['required', 'array'],
            ...$side('me', true),
            'me.crew_build_id' => ['nullable', 'integer', Rule::exists('crew_builds', 'id')->where('user_id', $this->user()?->id)],

            'opponent' => ['required', 'array'],
            ...$side('opponent', false),
            'opponent.opponent_name' => ['required_without:opponent.linked_user_id', 'nullable', 'string', 'max:80'],
            // an account to link; it only counts once that person confirms
            'opponent.linked_user_id' => ['nullable', 'integer', 'exists:users,id', Rule::notIn([$this->user()?->id])],

            'turns' => ['nullable', 'array', 'max:8'],
            'turns.*.turn_number' => ['required', 'integer', 'min:1', 'max:8', 'distinct'],
            ...collect(['me', 'opponent'])->flatMap(fn (string $who) => [
                "turns.*.$who.strategy_points" => ['nullable', 'integer', 'min:0', 'max:20'],
                "turns.*.$who.scheme_points" => ['nullable', 'integer', 'min:0', 'max:20'],
                "turns.*.$who.scheme_id" => ['nullable', 'integer', 'exists:schemes,id'],
                "turns.*.$who.scheme_action" => ['nullable', 'string', Rule::in(['scored', 'held', 'discarded'])],
                "turns.*.$who.strategy_bonus_used" => ['boolean'],
                "turns.*.$who.notes" => ['nullable', 'string', 'max:1000'],
            ])->all(),
        ];
    }

    /** The points entered turn by turn must add up to the totals, and the two sides must not both be the attacker. */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (['me', 'opponent'] as $who) {
                $sum = 0;
                $any = false;
                foreach ($this->input('turns', []) as $turn) {
                    $row = $turn[$who] ?? [];
                    if (isset($row['strategy_points']) || isset($row['scheme_points'])) {
                        $any = true;
                    }
                    $sum += (int) ($row['strategy_points'] ?? 0) + (int) ($row['scheme_points'] ?? 0);
                }
                if ($any && $sum !== (int) $this->input("$who.total_points")) {
                    $validator->errors()->add("$who.total_points", "The turns add up to $sum points, not ".(int) $this->input("$who.total_points").'.');
                }
            }

            $mine = $this->input('me.role');
            if ($mine !== null && $mine === $this->input('opponent.role')) {
                $validator->errors()->add('opponent.role', 'Both sides cannot be the '.$mine.'.');
            }
        }];
    }
}
