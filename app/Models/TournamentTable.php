<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the organizer says about one table: a name, notes on its terrain and an optional photo or layout diagram.
 * Rows only exist for tables the organizer has described; the table numbers themselves live on the games.
 *
 * @property int $id
 * @property int $tournament_id
 * @property int $number
 * @property string|null $name
 * @property string|null $description
 * @property string|null $image_path
 * @property int|null $tabletop_layout_id
 * @property-read string|null $image_url
 * @property-read Tournament $tournament
 * @property-read TabletopLayout|null $layout
 */
class TournamentTable extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['image_url'];

    protected $hidden = ['image_path'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * The saved Tabletop layout the organizer attached to this table, if any.
     *
     * @return BelongsTo<TabletopLayout, $this>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(TabletopLayout::class, 'tabletop_layout_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/'.$this->image_path : null;
    }
}
