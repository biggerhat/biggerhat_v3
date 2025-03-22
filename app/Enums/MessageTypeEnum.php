<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum MessageTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case success = 'success';
    case info = 'info';
    case warn = 'warn';
    case error = 'error';
    case secondary = 'secondary';
    case contrast = 'contrast';
}
