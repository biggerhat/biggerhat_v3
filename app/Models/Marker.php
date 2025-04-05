<?php

namespace App\Models;

use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marker extends Model
{
    /** @use HasFactory<\Database\Factories\MarkerFactory> */
    use HasFactory;

    use UsesSlugName;

    protected $guarded = ['id'];
}
