<?php

namespace App\Models\Campaign;

use Database\Factories\Campaign\WeeklyEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for a Weekly Event (pg 148–149). Rolled at the start of a
 * week when the campaign has the weekly_events optional rule enabled. Some
 * events place special terrain markers; "Bullet with Your Name on It" only
 * fires once per campaign (reflips on second occurrence).
 */
class WeeklyEvent extends Model
{
    /** @use HasFactory<WeeklyEventFactory> */
    use HasFactory;

    protected $table = 'weekly_events_catalog';

    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'is_black_joker' => 'boolean',
            'is_red_joker' => 'boolean',
            'terrain_marker_def' => 'array',
            'requires_placement' => 'boolean',
            'is_one_time' => 'boolean',
        ];
    }

    protected static function newFactory(): WeeklyEventFactory
    {
        return WeeklyEventFactory::new();
    }
}
