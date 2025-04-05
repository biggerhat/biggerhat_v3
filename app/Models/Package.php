<?php

namespace App\Models;

use App\Traits\UsesMiniatures;
use App\Traits\UsesSlugName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    /** @use HasFactory<\Database\Factories\PackageFactory> */
    use HasFactory;

    use UsesMiniatures;
    use UsesSlugName;

    protected $guarded = ['id'];
}
