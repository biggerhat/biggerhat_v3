<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSelectOptionsScope;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ability extends Model
{
    /** @use HasFactory<\Database\Factories\AbilityFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSelectOptionsScope;
    use UsesSlugName;

    protected $guarded = ['id'];
}
