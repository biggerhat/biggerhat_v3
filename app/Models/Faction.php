<?php

namespace App\Models;

use App\Traits\UsesSlugTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faction extends Model
{
    /** @use HasFactory<\Database\Factories\FactionFactory> */
    use HasFactory;

    use UsesSlugTitle;

    protected $guarded = [
        'id',
    ];
}
