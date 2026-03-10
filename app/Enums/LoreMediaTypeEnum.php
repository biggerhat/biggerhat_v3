<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

enum LoreMediaTypeEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Rulebook = 'rulebook';
    case FactionBook = 'faction_book';
    case Chronicle = 'chronicle';
    case Broadcast = 'broadcast';
    case PennyDreadful = 'penny_dreadful';
    case WyrdNews = 'wyrd_news';
    case Other = 'other';
}
