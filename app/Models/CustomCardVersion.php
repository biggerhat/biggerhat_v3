<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A card's state from before one of its saved edits (see App\Models\Concerns\HasCardVersions).
 *
 * @property int $id
 * @property string $versionable_type
 * @property int $versionable_id
 * @property array<string, mixed> $snapshot
 * @property \Carbon\CarbonInterface $created_at
 */
class CustomCardVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }
}
