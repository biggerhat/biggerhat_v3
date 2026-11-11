<?php

namespace App\Http\Requests\Tournaments\Concerns;

use App\Tournaments\Rulesets\Ruleset;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/**
 * Validation for the per-tournament customizations a TO can layer over a ruleset:
 * enabled `variants` (from the ruleset's catalog) and `scoring_config` overrides.
 * Used by the store and update requests; the including class supplies the ruleset.
 */
trait ValidatesRulesetCustomizations
{
    abstract protected function rulesetForCustomizations(): Ruleset;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function customizationRules(): array
    {
        return [
            'variants' => ['sometimes', 'nullable', 'array'],
            'scoring_config' => ['sometimes', 'nullable', 'array:base_tp,win_tp,tie_tp,loss_tp,diff_adds_to_tp,diff_cap,forfeit_vp,forfeit_loser_tp,tiebreakers'],
            'scoring_config.base_tp' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scoring_config.win_tp' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scoring_config.tie_tp' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scoring_config.loss_tp' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scoring_config.diff_adds_to_tp' => ['sometimes', 'boolean'],
            'scoring_config.diff_cap' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'scoring_config.forfeit_vp' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'scoring_config.forfeit_loser_tp' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'scoring_config.tiebreakers' => ['sometimes', 'array', 'max:3'],
            'scoring_config.tiebreakers.*' => ['string', 'in:sos,diff,vp', 'distinct'],
        ];
    }

    /**
     * A team size above 1 needs a ruleset that supports team events.
     *
     * @return callable(ValidatorContract): void
     */
    protected function validateTeamSize(): callable
    {
        return function (ValidatorContract $validator): void {
            if ((int) $this->input('team_size', 1) > 1 && ! $this->rulesetForCustomizations()->supportsTeams) {
                $validator->errors()->add('team_size', 'This format can’t be run as a team event.');
            }
        };
    }

    /**
     * Every enabled variant must exist in the ruleset's catalog; parameterised variants need a number in range.
     *
     * @return callable(ValidatorContract): void
     */
    protected function validateVariants(): callable
    {
        return function (ValidatorContract $validator): void {
            $variants = $this->input('variants');
            if (! is_array($variants)) {
                return;
            }

            $catalog = $this->rulesetForCustomizations()->variantsByKey();
            foreach ($variants as $key => $value) {
                $def = $catalog[$key] ?? null;
                if ($def === null) {
                    $validator->errors()->add("variants.{$key}", "'{$key}' is not an option for this format.");

                    continue;
                }
                if ($def['param'] !== null) {
                    $p = $def['param'];
                    if (! is_numeric($value) || (int) $value < $p['min'] || (int) $value > $p['max']) {
                        $validator->errors()->add("variants.{$key}", "{$def['label']} needs a number from {$p['min']} to {$p['max']}.");
                    }
                } elseif (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                    $validator->errors()->add("variants.{$key}", "'{$key}' must be on or off.");
                }
            }
        };
    }
}
