<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum DeploymentEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Standard = 'standard';
    case Corner = 'corner';
    case Flank = 'flank';
    case Wedge = 'wedge';

    public function suit(): SuitEnum
    {
        return match ($this) {
            self::Standard => SuitEnum::Ram,
            self::Corner => SuitEnum::Crow,
            self::Flank => SuitEnum::Tome,
            self::Wedge => SuitEnum::Mask,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Standard => 'A player will deploy within 8" of a chosen table edge, with the opponent deploying within 8" of the opposite table edge.',
            self::Corner => 'A player will deploy within 12" of a chosen table corner, with the opponent deploying within 12" of the opposite table corner.',
            self::Flank => 'The table is divided into four quarters. A player will deploy within 9" of the table edges within one quarter, with the opponent deploying in the opposite quarter.',
            self::Wedge => 'A player will deploy in a wedge starting 12" from the center of the table edge and sweeping back to the corners, with the opponent deploying opposite.',
        };
    }

    public function imageUrl(): string
    {
        $name = ucfirst($this->value);

        return "/storage/deployments/deploy-{$name}.png";
    }
}
