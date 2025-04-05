<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faction extends Model
{
    /** @use HasFactory<\Database\Factories\FactionFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSlugName;

    protected $guarded = [
        'id',
    ];
}
