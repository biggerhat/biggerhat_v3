<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * For relation lists the admin forms key by display name rather than id: the
 * value must resolve to exactly one record. `exists` alone passes when names
 * collide, and the controller's `whereIn` would then silently attach every
 * same-named row.
 */
class SingleMatch implements ValidationRule
{
    /** @param class-string<Model> $model */
    public function __construct(private readonly string $model, private readonly string $column) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $matches = $this->model::query()->where($this->column, $value)->limit(2)->count();

        if ($matches === 0) {
            $fail('The selected :attribute is invalid.');
        } elseif ($matches > 1) {
            $fail('More than one record is named ":input", so it cannot be linked by name. Rename one of them first.');
        }
    }
}
