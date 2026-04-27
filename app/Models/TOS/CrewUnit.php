<?php

namespace App\Models\TOS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperCrewUnit
 */
class CrewUnit extends Model
{
    protected $table = 'tos_crew_units';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_commander' => 'boolean',
        ];
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'tos_crew_unit_assets', 'crew_unit_id', 'asset_id')->withTimestamps();
    }
}
