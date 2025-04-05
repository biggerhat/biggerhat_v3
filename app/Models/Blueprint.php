<?php

namespace App\Models;

use App\Traits\UsesMiniatures;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blueprint extends Model
{
    /** @use HasFactory<\Database\Factories\BlueprintFactory> */
    use HasFactory;

    use UsesMiniatures;

    protected $guarded = ['id'];
}
