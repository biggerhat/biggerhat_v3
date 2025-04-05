<?php

namespace App\Models;

use App\Traits\UsesCharacters;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    /** @use HasFactory<\Database\Factories\TokenFactory> */
    use HasFactory;

    use UsesCharacters;
    use UsesSlugName;

    protected $guarded = ['id'];
}
