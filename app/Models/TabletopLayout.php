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
 * @property bool $is_shared anyone with the link may look at it and copy it
 * @property bool $is_public listed in the public gallery (always shared too)
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
            'is_public' => 'boolean',
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

    /** How widely it is visible: private, link (anyone with the link) or public (also listed in the gallery). */
    public function visibility(): string
    {
        return $this->is_public ? 'public' : ($this->is_shared ? 'link' : 'private');
    }

    /**
     * The two flags a visibility stands for.
     *
     * @return array{is_shared: bool, is_public: bool}
     */
    public static function flagsFor(string $visibility): array
    {
        return ['is_shared' => $visibility !== 'private', 'is_public' => $visibility === 'public'];
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
