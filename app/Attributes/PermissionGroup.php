<?php

namespace App\Attributes;

use App\Enums\PermissionGroupEnum;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class PermissionGroup
{
    public function __construct(public PermissionGroupEnum $permissionGroup) {}
}
