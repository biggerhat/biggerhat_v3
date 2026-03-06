<?php

namespace App\Traits;

use JetBrains\PhpStorm\ArrayShape;

trait UsesEnumSelectOptions
{
    #[ArrayShape(['name' => 'string', 'value' => 'string'])]
    public static function toSelectOptions(): array
    {
        return collect(self::cases())->map(fn ($category) => ['name' => $category->label(), 'value' => $category->value])->toArray();
    }
}
