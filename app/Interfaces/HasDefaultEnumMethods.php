<?php

namespace App\Interfaces;

interface HasDefaultEnumMethods
{
    public function label(): string;

    public static function toSelectOptions(): array;
}
