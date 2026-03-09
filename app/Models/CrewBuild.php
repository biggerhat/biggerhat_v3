<?php

namespace App\Models;

use App\Enums\FactionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrewBuild extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'faction' => FactionEnum::class,
            'crew_data' => 'array',
            'encounter_size' => 'integer',
            'is_archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrewBuild $build) {
            if (! $build->share_code) {
                $build->share_code = Str::random(12);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'master_id');
    }
}
