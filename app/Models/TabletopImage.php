<?php

namespace App\Models;

use Database\Factories\TabletopImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-uploaded art for the tabletop: a table surface, or a texture for one kind of terrain.
 *
 * @property int $id
 * @property string $name
 * @property string $kind 'table' or 'terrain'
 * @property string|null $terrain_key the terrain kind a terrain texture is for
 * @property string $image_path path on the public disk (tabletop/foo_uuid.webp)
 * @property string|null $credit
 * @property bool $is_active
 * @property int $sort_order
 */
class TabletopImage extends Model
{
    /** @use HasFactory<TabletopImageFactory> */
    use HasFactory;

    public const KIND_TABLE = 'table';

    public const KIND_TERRAIN = 'terrain';

    protected $table = 'tabletop_images';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<TabletopImage>  $query
     * @return Builder<TabletopImage>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
