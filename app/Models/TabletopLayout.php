<?php

namespace App\Models;

use Database\Factories\TabletopLayoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A saved terrain setup: the pieces on the table and the surface they sit on.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property list<array<string, mixed>> $terrain
 * @property string|null $table_image
 * @property bool $is_shared
 * @property string|null $copied_from
 * @property-read User $user
 */
class TabletopLayout extends Model
{
    /** @use HasFactory<TabletopLayoutFactory> */
    use HasFactory;

    /** Layouts one person may keep. */
    public const PER_USER = 50;

    protected $table = 'tabletop_layouts';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'terrain' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TabletopLayout $layout) {
            if (! $layout->uuid) {
                $layout->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
